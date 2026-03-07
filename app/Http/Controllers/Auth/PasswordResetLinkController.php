<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    private const GENERIC_RESET_STATUS = 'Si el correo existe en nuestro sistema, enviamos un enlace para restablecer la contrasena.';

    /**
     * Display the password reset link request view.
     */
    public function create(Request $request): View
    {
        $restablecer = $request->restablecer;
        return view('auth.forgot-password', compact('restablecer'));
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', self::GENERIC_RESET_STATUS);
    }
}
