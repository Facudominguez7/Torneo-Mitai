<?php

namespace App\Http\Controllers\Google;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailDomainGuard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class LoginController extends Controller
{
    public function callback()
    {
        try {
            $user_google = Socialite::driver('google')->stateless()->user();

            if (EmailDomainGuard::isBlocked((string) $user_google->email)) {
                return redirect('login')->withErrors(['email' => EmailDomainGuard::validationMessage()]);
            }

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
                $user = new User;
                $user->google_id = $user_google->id;
                $user->name = $user_google->name;
                $user->email = $user_google->email;
                $user->email_verified_at = Carbon::now();
                $user->google_avatar = $user_google->avatar;
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
        return Socialite::driver('google')->redirect();
    }
}
