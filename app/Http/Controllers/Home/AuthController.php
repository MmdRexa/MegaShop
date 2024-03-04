<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function login(){
        return view('auth.login');
    }

    public function register(){
        return view('auth.register');
    }

    public function redirectToProvider($provider){
        return Socialite::driver($provider)->redirect();
    }

    public function handleProviderCallback(Request $request,$provider){

        $socialiteUser = Socialite::driver($provider)->stateless()->user();

        $user = User::where('email', $socialiteUser->getEmail())->first();

        $string = random_int(1000,9999999);

        $duplicateUsername = User::where('username', $socialiteUser->getName())->first();

        if (!$user){
            $user = User::create([
                'username' => $duplicateUsername ? 'Guest-' . $string : $socialiteUser->getName(),
                'first_name' => $socialiteUser->user['given_name'],
                'last_name' => $socialiteUser->user['family_name'],
                'email' => $socialiteUser->getEmail(),
                'avatar' => $socialiteUser->getAvatar(),
                'password' => Hash::make($socialiteUser->getId()),
                'provider_name' => $provider,
                'email_verified_at' => Carbon::now()
            ]);

            $request->session()->flash('welcome',  '! ' . $user->username .' خوش اومدی');
        }

        $request->session()->flash('welcome',  '! ' . $user->username .' خوش برگشتی');

        auth()->loginUsingId($user->id);

        toastr()->success('ورود با موفقیت انجام شد!');

        return redirect()->route('home.index');

    }
}
