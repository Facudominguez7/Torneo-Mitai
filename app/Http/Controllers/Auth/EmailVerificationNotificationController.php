<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('home', absolute: false));
        }

        try {
            $request->user()->sendEmailVerificationNotification();
            
            return back()->with('status', 'verification-link-sent')
                         ->with('email-success', '¡Email enviado! Revisá tu bandeja de entrada y la carpeta de spam.');
        } catch (\Exception $e) {
            \Log::error('Error al enviar email de verificación: ' . $e->getMessage());
            
            return back()->with('email-error', 'No pudimos enviar el email. Por favor, intentá de nuevo más tarde.');
        }
    }
}
