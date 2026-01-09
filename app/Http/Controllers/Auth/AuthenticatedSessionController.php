<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     * 
     * Valida credenciales, verifica si necesita restablecer contraseña,
     * y advierte si el email no está verificado para recibir notificaciones.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only(['email', 'password']);
    
        if (!Auth::attempt($credentials)) {
            return back()->withErrors(['email' => 'Invalid credentials']);
        }
    
        // Verificar si el usuario necesita restablecer su contraseña
        $user = Auth::user();
        
        if ($user->restablecer_password === 'necesario') {
            Auth::logout();
            return redirect()->route('password.request.auth', ['restablecer' => 'Si']);
        }
    
        $request->session()->regenerate();

        // Advertir al usuario si no tiene email verificado y reenviar correo
        if (!$user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();

            return redirect()->intended(route('home', absolute: false))
                ->with('warning', '⚠️ Tu email no está verificado. Te hemos enviado un nuevo enlace de verificación. Revisá tu bandeja de entrada o spam para recibir notificaciones.');
        }
    
        return redirect()->intended(route('home', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
