<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\ProductImageController;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Genre;
use App\Models\Platform;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductImage;
use App\Models\ProductVariation;
use App\Models\Tag;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpParser\Node\Expr\New_;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = Product::latest()->paginate(10);
        return view('admin.products.index' , compact('products'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $brands = Brand::where('is_active' , 1)->get();
        $platforms = Platform::where('is_active' , 1)->get();
        $tags = Tag::all();
        $categories = Category::where([['is_active' , 1],['parent_id', '!=', '0']])->get();
        return view('admin.products.create' , compact('brands','tags', 'categories', 'platforms'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'brand_id' => 'required',
            'is_active' => 'required',
            'tag_ids' => 'required',
            'platform_id' => 'nullable',
            'description' => 'required',
            'primary_img' => 'required|mimes:jpg,jpeg,png,svg',
            'other_imgs' => 'required',
            'other_imgs.*' => 'mimes:jpg,jpeg,png,svg',
            'category_id' => 'required',
            'attribute_ids' => 'required',
            'attribute_ids.*' => 'required',
            'variation_values' => 'required',
            'variation_values.*.*' => 'required',
            'variation_values.quantity.*' => 'integer',
            'variation_values.price.*' => 'integer',
            'variation_values.sku.*' => 'integer',
            'delivery_amount' => 'required|integer',
            'delivery_amount_per_product' => 'nullable|integer',
        ]);
        try {
            DB::beginTransaction();

            $productImageController = new ProductImageController();
            $imgsFileName = $productImageController->upload($request->primary_img , $request->other_imgs);

            $product = Product::create([
                'name' => $request->name,
                'brand_id' => $request->brand_id,
                'platform_id' => $request->platform_id,
                'is_active' => $request->is_active,
                'category_id' => $request->category_id,
                'primary_image' => $imgsFileName['primaryImg'],
                'description' => $request->description,
                'delivery_amount' => $request->delivery_amount,
                'delivery_amount_per_product' => $request->delivery_amount_per_product,
            ]);

            foreach($imgsFileName['otherImgs'] as $imgFileName){
                ProductImage::create([
                    'image' => $imgFileName,
                    'product_id' => $product->id
                ]);
            }

            $ProductAttributeController = new ProductAttributeController();
            $ProductAttributeController->store($request->attribute_ids , $product->id);

            $category = Category::find($request->category_id);
            $ProductVariationController = new ProductVariationController();
            $ProductVariationController->store($request->variation_values, $category->attributes()->wherePivot('is_variation' , 1)->first()->id,$product);

            $product->tags()->attach($request->tag_ids);

            DB::commit();
        }catch (\Exception $ex) {
            DB::rollBack();
            toastr()->error('مشکلی پیش آمد!',$ex->getMessage());
            return redirect()->route('admin.products.create');
        }

        toastr()->success('با موفقیت محصول اضافه شد.');
        return redirect()->back();
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        $productAttributes = $product->attributes()->with('attribute')->get();
        $productVariations = $product->variations;
        return view('admin.products.show' , compact('product', 'productAttributes', 'productVariations'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        $brands = Brand::where('is_active' , 1)->get();
        $platforms = Platform::where('is_active' , 1)->get();
        $tags = Tag::all();
        $productAttributes = $product->attributes()->with('attribute')->get();
        $productVariations = $product->variations;
        return view('admin.products.edit', compact('product', 'brands', 'tags', 'productAttributes', 'productVariations', 'platforms'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
//        dd($request->all());
        $request->validate([
            'name' => 'required',
            'brand_id' => 'required',
            'platform_id' => 'nullable',
            'is_active' => 'required',
            'tag_ids' => 'required',
            'description' => 'required',
            'delivery_amount' => 'required|integer',
            'delivery_amount_per_product' => 'nullable|integer',
            'attribute_values' => 'required',
            'attribute_values.*' => 'required',
            'variation_values' => 'required',
            'variation_values.*.price' => 'required|integer',
            'variation_values.*.quantity' => 'required|integer',
            'variation_values.*.sku' => 'required|integer',
            'variation_values.*.sale_price' => 'nullable|integer',
            'variation_values.*.date_on_sale_from' => 'nullable|date',
            'variation_values.*.date_on_sale_to' => 'nullable|date',
        ]);
        try {
            DB::beginTransaction();

            $product->update([
                'name' => $request->name,
                'brand_id' => $request->brand_id,
                'platform_id' => $request->platform_id,
                'is_active' => $request->is_active,
                'description' => $request->description,
                'delivery_amount' => $request->delivery_amount,
                'delivery_amount_per_product' => $request->delivery_amount_per_product,
            ]);

            $product->tags()->sync($request->tag_ids);

            $ProductAttributeController = new ProductAttributeController();
            $ProductAttributeController->update($request->attribute_values);

            $ProductVariationController = new ProductVariationController();
            $ProductVariationController->update($request->variation_values);

            DB::commit();
        }catch (\Exception $ex) {
            DB::rollBack();
            toastr()->error('مشکلی پیش آمد!',$ex->getMessage());
            return redirect()->back();
        }

        toastr()->success('با موفقیت محصول ویرایش شد.');
        return redirect()->back();
    }

    public function search(Request $request)
    {
        $keyWord = request()->keyword;
        if (request()->has('keyword') && trim($keyWord) != ''){
            $products = Product::where('name', 'LIKE', '%'.trim($keyWord).'%')->latest()->paginate(10);
            return view('admin.products.index' , compact('products'));
        }else{
            $products = Product::latest()->paginate(10);
            return view('admin.products.index' , compact('products'));
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function edit_category(Request $request, Product $product)
    {
        $categories = Category::where([['is_active' , 1],['parent_id', '!=', '0']])->get();
        return view('admin.products.edit_category', compact('product', 'categories'));
    }

    public function update_category(Request $request, Product $product)
    {
        $request->validate([
            'category_id' => 'required',
            'attribute_ids' => 'required',
            'attribute_ids.*' => 'required',
            'variation_values' => 'required',
            'variation_values.*.*' => 'required',
            'variation_values.quantity.*' => 'integer',
            'variation_values.price.*' => 'integer',
            'variation_values.sku.*' => 'integer',
        ]);
        try {
            DB::beginTransaction();

            $product->update([
                'category_id' => $request->category_id
            ]);

            $ProductAttributeController = new ProductAttributeController();
            $ProductAttributeController->change($request->attribute_ids , $product->id);

            $category = Category::find($request->category_id);
            $ProductVariationController = new ProductVariationController();
            $ProductVariationController->change($request->variation_values, $category->attributes()->wherePivot('is_variation' , 1)->first()->id,$product);

            DB::commit();
        }catch (\Exception $ex) {
            DB::rollBack();
            toastr()->error('مشکلی پیش آمد!',$ex->getMessage());
            return redirect()->back();
        }

        toastr()->success('با موفقیت دسته بندی محصول ویرایش شد.');
        return redirect()->back();
    }
}
