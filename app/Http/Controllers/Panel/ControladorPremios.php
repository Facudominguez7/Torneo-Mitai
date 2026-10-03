<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Premios\GuardarGoleadorRequest;
use App\Http\Requests\Premios\GuardarVallaRequest;
use App\Models\Edicion;
use App\Services\Panel\ConfirmadorPremios;
use App\Services\Panel\SugerenciaPremios;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Premios individuales: fusión de Goleador del torneo y Valla menos vencida
 * por categoría (T8c, panel-admin-redesign). Controlador fino: la sugerencia
 * vive en SugerenciaPremios y el guardado transaccional (con su lock contra
 * altas concurrentes) vive en ConfirmadorPremios.
 */
class ControladorPremios extends Controller
{
    public function __construct(
        private readonly SugerenciaPremios $sugerenciaPremios,
        private readonly ConfirmadorPremios $confirmadorPremios,
    ) {}

    public function index(Request $request): View
    {
        $idEdicion = $this->resolverIdEdicion($request);
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;

        $premios = $idEdicion
            ? $this->sugerenciaPremios->obtenerPremios($idEdicion)
            : ['categorias' => collect(), 'tieneContenido' => false];

        return view('Panel.premios.index', [
            'EdicionSeleccionada' => $EdicionSeleccionada,
            'categorias' => $premios['categorias'],
            'tieneContenido' => $premios['tieneContenido'],
        ]);
    }

    /**
     * Guarda el goleador elegido (de la sugerencia) para una categoría. El
     * dni llega del cliente, pero el nombre y el equipo del goleador siempre
     * se resuelven en el servidor a partir de la tabla de goleadores.
     */
    public function guardarGoleador(GuardarGoleadorRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $parametrosRegreso = ['idEdicion' => $data['idEdicion']];

        try {
            $resultado = $this->confirmadorPremios->guardarGoleador(
                $data['idEdicion'],
                $data['idCategoria'],
                $data['dni_jugador'],
            );
        } catch (Throwable $error) {
            Log::error('No se pudo guardar el goleador sugerido', [
                'idEdicion' => $data['idEdicion'],
                'idCategoria' => $data['idCategoria'],
                'dni_jugador' => $data['dni_jugador'],
                'exception' => $error,
            ]);

            return to_route('premios.index', $parametrosRegreso)
                ->with('error', 'No se pudo guardar el goleador. Intentá nuevamente.');
        }

        return match ($resultado['resultado']) {
            'confirmado' => to_route('premios.index', $parametrosRegreso)->with('status', 'Goleador guardado.'),
            'ya_existe' => to_route('premios.index', $parametrosRegreso)->with('error', 'Esta categoría ya tiene un goleador cargado.'),
            default => to_route('premios.index', $parametrosRegreso)->with('error', 'El jugador elegido ya no está disponible en la tabla de goleadores.'),
        };
    }

    /**
     * Guarda la valla elegida (de la sugerencia) para una categoría, junto
     * con el nombre del arquero. El equipo siempre se verifica en el
     * servidor contra los equipos de esta edición y categoría.
     */
    public function guardarValla(GuardarVallaRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $parametrosRegreso = ['idEdicion' => $data['idEdicion']];

        try {
            $resultado = $this->confirmadorPremios->guardarValla(
                $data['idEdicion'],
                $data['idCategoria'],
                $data['idEquipo'],
                $data['nombre'],
            );
        } catch (Throwable $error) {
            Log::error('No se pudo guardar la valla sugerida', [
                'idEdicion' => $data['idEdicion'],
                'idCategoria' => $data['idCategoria'],
                'idEquipo' => $data['idEquipo'],
                'exception' => $error,
            ]);

            return to_route('premios.index', $parametrosRegreso)
                ->with('error', 'No se pudo guardar la valla. Intentá nuevamente.');
        }

        return match ($resultado['resultado']) {
            'confirmado' => to_route('premios.index', $parametrosRegreso)->with('status', 'Valla guardada.'),
            'ya_existe' => to_route('premios.index', $parametrosRegreso)->with('error', 'Esta categoría ya tiene una valla cargada.'),
            default => to_route('premios.index', $parametrosRegreso)->with('error', 'El equipo elegido no pertenece a esta edición y categoría.'),
        };
    }

    /**
     * Misma resolución de contexto que ControladorPodio::resolverIdEdicion:
     * confía en el atributo que deja PanelEdicionContextMiddleware (incluso
     * si es null) y solo recurre a la query/sesión si el middleware no corrió.
     */
    private function resolverIdEdicion(Request $request): ?int
    {
        $idEdicion = $request->attributes->has('panel.idEdicion')
            ? $request->attributes->get('panel.idEdicion')
            : ($request->query('idEdicion') ?: $request->session()->get('panel.idEdicion'));

        return $idEdicion ? (int) $idEdicion : null;
    }
}
