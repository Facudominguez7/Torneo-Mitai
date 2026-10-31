<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UserAccesPanelMiddleware
{
    /**
     * Handle an incoming request.
     *
     * El panel no es una superficie pública: los invitados (incluidos
     * crawlers) reciben 404 en lugar de una redirección, para que las
     * URLs del panel no queden registradas como "Página con redirección"
     * en Search Console. Los usuarios autenticados sin rol admin siguen
     * viendo la redirección a la home.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            abort(404);
        }

        if (Auth::user()->isAdmin()) {
            return $next($request);
        }

        return redirect('/');
    }
}
