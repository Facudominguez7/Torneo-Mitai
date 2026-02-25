<?php

namespace App\Http\Middleware;

use App\Models\Edicion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PanelEdicionContextMiddleware
{
    /**
     * Resolver y persistir el contexto de edición activo del panel.
     *
     * Reglas:
     * - Si llega `idEdicion` por query, se toma como edición activa y se guarda en sesión.
     * - Si no llega por query, se reutiliza la edición activa guardada en sesión.
     * - Si el id no existe en base de datos, se limpia de sesión.
     *
     * Además, deja disponible el contexto en atributos del request para que
     * vistas/composers puedan reutilizarlo sin repetir lógica.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $idEdicionQuery = $request->query('idEdicion');

        if (!is_null($idEdicionQuery) && $idEdicionQuery !== '') {
            $edicion = Edicion::find((int) $idEdicionQuery);

            if ($edicion) {
                $request->session()->put('panel.idEdicion', $edicion->id);
                $request->attributes->set('panel.idEdicion', $edicion->id);
                $request->attributes->set('panel.edicion', $edicion);

                return $next($request);
            }

            $request->session()->forget('panel.idEdicion');
            $request->attributes->set('panel.idEdicion', null);
            $request->attributes->set('panel.edicion', null);

            return $next($request);
        }

        $idEdicionSesion = $request->session()->get('panel.idEdicion');
        if ($idEdicionSesion) {
            $edicion = Edicion::find((int) $idEdicionSesion);

            if ($edicion) {
                $request->attributes->set('panel.idEdicion', $edicion->id);
                $request->attributes->set('panel.edicion', $edicion);

                return $next($request);
            }

            $request->session()->forget('panel.idEdicion');
        }

        $request->attributes->set('panel.idEdicion', null);
        $request->attributes->set('panel.edicion', null);

        return $next($request);
    }
}
