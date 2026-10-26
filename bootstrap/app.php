<?php

use App\Http\Middleware\UserAccesPanelMiddleware;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

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

        // Prioridad explícita: el bloqueo del panel (404 a invitados) debe
        // correr después de la sesión pero antes de Authenticate, para que
        // los invitados y crawlers reciban 404 y no la redirección al login.
        $middleware->priority([
            HandlePrecognitiveRequests::class,
            EncryptCookies::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            ValidateCsrfToken::class,
            UserAccesPanelMiddleware::class,
            Authenticate::class,
            SubstituteBindings::class,
            Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
