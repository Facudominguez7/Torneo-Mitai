<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\EmailDomainGuard;
use App\Services\EmailVerificationCodeService;
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
    public function store(LoginRequest $request, EmailVerificationCodeService $verificationCodeService): RedirectResponse
    {
        $request->authenticate();

        // Verificar si el usuario necesita restablecer su contraseña
        $user = $request->user();

        if ($user->restablecer_password === 'necesario') {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('password.request.auth', ['restablecer' => 'Si']);
        }

        $request->session()->regenerate();

        // Advertir al usuario si no tiene email verificado y reenviar codigo
        if (! $user->hasVerifiedEmail()) {
            if (EmailDomainGuard::isBlocked((string) $user->email)) {
                return redirect()->route('verification.notice')
                    ->with('email-error', 'Tu correo no es elegible para recibir emails automáticos. Actualizalo desde tu perfil para continuar.');
            }

            $secondsUntilResend = $verificationCodeService->secondsUntilResend($user);

            if ($secondsUntilResend === 0) {
                $user->sendEmailVerificationNotification();

                return redirect()->route('verification.notice')
                    ->with('status', 'verification-code-sent')
                    ->with('warning', 'Tu email todavia no esta verificado. Te enviamos un codigo para activarlo.');
            }

            return redirect()->route('verification.notice')
                ->with('warning', 'Tu email todavia no esta verificado. Usa el ultimo codigo enviado o pedi uno nuevo en unos segundos.');
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
