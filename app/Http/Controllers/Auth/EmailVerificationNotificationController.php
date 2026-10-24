<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EmailDomainGuard;
use App\Services\EmailVerificationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request, EmailVerificationCodeService $verificationCodeService): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('home', absolute: false));
        }

        if (EmailDomainGuard::isBlocked((string) $request->user()->email)) {
            return back()->with('email-error', 'Ese dominio de correo no está habilitado para recibir emails automáticos.');
        }

        $secondsUntilResend = $verificationCodeService->secondsUntilResend($request->user());

        if ($secondsUntilResend > 0) {
            return back()->with('email-error', 'Espera '.$secondsUntilResend.' segundos antes de pedir un nuevo codigo.');
        }

        try {
            $request->user()->sendEmailVerificationNotification();

            return back()->with('status', 'verification-code-sent')
                ->with('email-success', 'Te enviamos un nuevo codigo de verificacion. Revisa tu bandeja de entrada y spam.');
        } catch (\Exception $e) {
            \Log::error('Error al enviar email de verificación: '.$e->getMessage());

            return back()->with('email-error', 'No pudimos enviar el codigo. Por favor, intenta de nuevo mas tarde.');
        }
    }
}
