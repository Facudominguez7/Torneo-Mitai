<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Fecha;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Centro de Planillas e impresiones (T9b, panel-admin-redesign). Es un hub
 * sobre las impresiones que ya existen: solo resuelve el contexto de edición
 * y le pasa a la vista las opciones (categorías y fechas) de los
 * filtros que cada ruta de impresión acepta de verdad.
 */
class ControladorImpresiones extends Controller
{
    public function index(Request $request): View
    {
        $idEdicion = $this->resolverIdEdicion($request);
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;

        $categorias = collect();
        $fechas = collect();

        if ($EdicionSeleccionada) {
            $categorias = Categoria::where('idEdicion', $EdicionSeleccionada->id)
                ->orderBy('nombreCategoria')
                ->get(['id', 'nombreCategoria']);

            $fechas = Fecha::where('idEdicion', $EdicionSeleccionada->id)
                ->select('id', 'nombre')
                ->orderBy('id')
                ->get();
        }

        return view('Panel.impresiones.index', [
            'EdicionSeleccionada' => $EdicionSeleccionada,
            'categorias' => $categorias,
            'fechas' => $fechas,
        ]);
    }

    /**
     * Misma resolución de contexto que ControladorPodio::resolverIdEdicion:
     * el middleware del panel ya validó la edición (query o sesión).
     */
    private function resolverIdEdicion(Request $request): ?int
    {
        $idEdicion = $request->attributes->has('panel.idEdicion')
            ? $request->attributes->get('panel.idEdicion')
            : ($request->query('idEdicion') ?: $request->session()->get('panel.idEdicion'));

        return $idEdicion ? (int) $idEdicion : null;
    }
}
