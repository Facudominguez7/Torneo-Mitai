<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EmailVerificationCodeService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationCodeController extends Controller
{
    public function store(Request $request, EmailVerificationCodeService $verificationCodeService): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('home', absolute: false));
        }

        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $status = $verificationCodeService->verify($user, $validated['code']);

        if ($status === EmailVerificationCodeService::STATUS_VERIFIED) {
            if ($user->markEmailAsVerified()) {
                event(new Verified($user));
            }

            return redirect()->intended(route('home', absolute: false).'?verified=1');
        }

        $message = match ($status) {
            EmailVerificationCodeService::STATUS_EXPIRED => 'El codigo vencio. Pedi uno nuevo para continuar.',
            EmailVerificationCodeService::STATUS_LOCKED => 'Agotaste los intentos disponibles. Pedi un codigo nuevo.',
            EmailVerificationCodeService::STATUS_MISSING => 'No encontramos un codigo activo para tu cuenta. Pedi uno nuevo.',
            default => 'El codigo ingresado no es valido.',
        };

        return back()->withErrors(['code' => $message]);
    }
}
