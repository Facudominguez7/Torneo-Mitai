<?php

namespace App\Http\Controllers\Google;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;


class LoginController extends Controller
{
    public function callback()
    {
        try {
            // Limpiar el caché
            Cache::flush();

            $user_google = Socialite::driver('google')->stateless()->user();

            // Verifica si el correo electrónico ya está en uso por un usuario registrado
            $existingUser = User::where('email', $user_google->email)->first();

            if ($existingUser) {
                if ($existingUser->google_id) {
                    Auth::login($existingUser);
                    return redirect()->intended(route('home'));
                } else {
                    return redirect('login')->withErrors(['email' => 'El correo electrónico ya está en uso.']);
                }
            } else {
                $password = Str::random(12);
                $user = new User();
                $user->google_id = $user_google->id;
                $user->name = $user_google->name;
                $user->email = $user_google->email;
                $user->google_avatar = $user_google->avatar;
                $user->google_token = $user_google->token;
                $user->password = Hash::make($password); // Opcional

                $user->save();
                Auth::login($user);

                return redirect()->intended(route('home'));
            }
        } catch (\Exception $e) {
            return Redirect::route('login')->with('error', 'Autenticación fallida.');
        }
    }

    public function redirect()
    {
        // Limpiar el caché
        Cache::flush();

        return Socialite::driver('google')->redirect();
    }
    
}
