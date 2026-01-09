<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(\Illuminate\Http\Request $request): RedirectResponse
    {
        $user = $request->user();

        // Validar ID del usuario vs ID en query param
        if (! hash_equals((string) $request->route('id') ?? (string) $request->input('id'), (string) $user->getKey())) {
            throw new \Illuminate\Auth\Access\AuthorizationException;
        }

        // Validar hash de email vs hash en query param
        if (! hash_equals((string) $request->route('hash') ?? (string) $request->input('hash'), sha1($user->getEmailForVerification()))) {
            throw new \Illuminate\Auth\Access\AuthorizationException;
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('home', absolute: false).'?verified=1');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended(route('home', absolute: false).'?verified=1');
    }
}
