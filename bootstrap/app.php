<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        //
    })->create();
