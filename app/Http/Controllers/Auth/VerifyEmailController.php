<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EmailVerificationCodeService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(Request $request, EmailVerificationCodeService $verificationCodeService): RedirectResponse
    {
        $user = $request->user();
        
        // Obtener id y hash desde query params
        $id = $request->input('id');
        $hash = $request->input('hash');

        // Validar que existan los parámetros
        if (!$id || !$hash) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Faltan parámetros de verificación');
        }

        // Validar ID del usuario vs ID en query param
        if (! hash_equals((string) $id, (string) $user->getKey())) {
            throw new \Illuminate\Auth\Access\AuthorizationException('ID de usuario no coincide');
        }

        // Validar hash de email vs hash en query param
        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Hash de verificación inválido');
        }

        if ($user->hasVerifiedEmail()) {
            $verificationCodeService->clear($user);

            return redirect()->intended(route('home', absolute: false).'?verified=1');
        }

        if ($user->markEmailAsVerified()) {
            $verificationCodeService->clear($user);
            event(new Verified($user));
        }

        return redirect()->intended(route('home', absolute: false).'?verified=1');
    }
}
