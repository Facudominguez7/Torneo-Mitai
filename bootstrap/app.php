<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Confiar en todos los proxies (necesario para Hostinger y otros hostings compartidos)
        // Esto permite que Laravel detecte correctamente HTTPS y valide las URLs firmadas
        $middleware->trustProxies(at: '*');

        // Excluir la ruta POST /email/baja de CSRF.
        // Gmail/Outlook envian un POST automatico para one-click unsubscribe (RFC 8058)
        // sin token CSRF, por lo que debe estar excluida.
        $middleware->validateCsrfTokens(except: [
            'email/baja',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Token CSRF vencido en el login (doble clic en "Acceder" o pestaña abierta más que la sesión):
        // en vez del 419 genérico, se lleva al destino si ya ingresó o se vuelve al login con un aviso.
        $exceptions->render(function (HttpException $e, Request $request): ?RedirectResponse {
            if ($e->getStatusCode() !== 419 || ! $request->is('login') || $request->expectsJson()) {
                return null;
            }

            if ($request->user()) {
                return redirect()->intended(route('home', absolute: false));
            }

            return redirect()->route('login')
                ->withInput($request->only('email'))
                ->with('aviso', 'La sesión expiró. Volvé a intentar.');
        });
    })->create();
