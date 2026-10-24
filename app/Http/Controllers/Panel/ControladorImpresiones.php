<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Fecha;
use App\Models\Partido;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Centro de Planillas e impresiones (T9b, panel-admin-redesign). Es un hub
 * sobre las impresiones que ya existen: solo resuelve el contexto de edición
 * y le pasa a la vista las opciones (categorías, fechas, horarios) de los
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
        $horariosDisponibles = collect();

        if ($EdicionSeleccionada) {
            $categorias = Categoria::where('idEdicion', $EdicionSeleccionada->id)
                ->orderBy('nombreCategoria')
                ->get(['id', 'nombreCategoria']);

            $fechas = Fecha::where('idEdicion', $EdicionSeleccionada->id)
                ->select('id', 'nombre')
                ->orderBy('id')
                ->get();

            $horariosDisponibles = $this->obtenerHorariosDisponibles($EdicionSeleccionada->id);
        }

        return view('Panel.impresiones.index', [
            'EdicionSeleccionada' => $EdicionSeleccionada,
            'categorias' => $categorias,
            'fechas' => $fechas,
            'horariosDisponibles' => $horariosDisponibles,
        ]);
    }

    /**
     * Mismos horarios (HH:mm) que ofrece el filtro de ControladorPartidos::index,
     * pero a nivel edición: las planillas de árbitros solo filtran por horario.
     *
     * @return Collection<int, string>
     */
    private function obtenerHorariosDisponibles(int $idEdicion): Collection
    {
        return Partido::where('idEdicion', $idEdicion)
            ->where(function ($query) {
                $query->whereNotNull('horario_datetime')
                    ->orWhereNotNull('horario');
            })
            ->get(['horario', 'horario_datetime'])
            ->map(static function (Partido $partido): ?string {
                if ($partido->horario_datetime) {
                    return Carbon::parse($partido->horario_datetime)->format('H:i');
                }

                // Horario legacy en texto ("17:30", "19.30", "9"): misma normalización que la agrupación de Partidos.
                if ($partido->horario !== null && $partido->horario !== ''
                    && preg_match('/^(\d{1,2})(?:[:.](\d{2}))?$/', trim((string) $partido->horario), $partes)) {
                    return sprintf('%02d:%s', (int) $partes[1], $partes[2] ?? '00');
                }

                return null;
            })
            ->filter()
            ->unique()
            ->sort()
            ->values();
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
