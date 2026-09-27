<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Podio\ConfirmarRequest;
use App\Models\Campeon;
use App\Models\Edicion;
use App\Models\Subcampeon;
use App\Services\Panel\ConfirmadorPodio;
use App\Services\Panel\SugerenciaPodio;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Podio: fusión de Campeones y Subcampeones por categoría y copa (T8b,
 * panel-admin-redesign). Controlador fino: toda la lógica de sugerencia vive
 * en SugerenciaPodio y la confirmación transaccional (con su lock contra
 * confirmaciones concurrentes) vive en ConfirmadorPodio. Acá solo se
 * resuelve el contexto de edición, se hace el chequeo temprano de "ya
 * existe" (para no pagar el costo de una transacción en el camino de
 * rechazo sin carrera) y se traduce el resultado a la respuesta.
 */
class ControladorPodio extends Controller
{
    public function __construct(
        private readonly SugerenciaPodio $sugerenciaPodio,
        private readonly ConfirmadorPodio $confirmadorPodio,
    ) {}

    public function index(Request $request): View
    {
        $idEdicion = $this->resolverIdEdicion($request);
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;

        $podio = $idEdicion
            ? $this->sugerenciaPodio->obtenerPodio($idEdicion)
            : ['categorias' => collect(), 'tieneContenido' => false];

        return view('Panel.podio.index', [
            'EdicionSeleccionada' => $EdicionSeleccionada,
            'categorias' => $podio['categorias'],
            'tieneContenido' => $podio['tieneContenido'],
        ]);
    }

    /**
     * Confirma el campeón y el subcampeón sugeridos para una copa. Recalcula
     * la sugerencia server-side (nunca confía en equipos que mande el
     * cliente) y no hace nada si ya existe algo cargado para esa copa o si ya
     * no hay sugerencia vigente.
     */
    public function confirmar(ConfirmarRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $parametrosRegreso = ['idEdicion' => $data['idEdicion']];
        $errorYaExiste = 'Esta copa ya tiene campeón o subcampeón cargado.';
        $errorSinSugerencia = 'No hay una sugerencia vigente para confirmar en esta copa.';

        // Chequeo temprano fuera de la transacción: en el camino sin carrera
        // (la gran mayoría) evita pagar el costo de abrir una transacción y
        // tomar el lock solo para terminar rechazando. ConfirmadorPodio
        // vuelve a hacer este mismo chequeo, ya con el lock tomado, para
        // cubrir la carrera real (doble click, dos admins a la vez).
        $yaExiste = Campeon::where('idEdicion', $data['idEdicion'])
            ->where('idCategoria', $data['idCategoria'])
            ->where('idCopa', $data['idCopa'])
            ->exists()
            || Subcampeon::where('idEdicion', $data['idEdicion'])
                ->where('idCategoria', $data['idCategoria'])
                ->where('idCopa', $data['idCopa'])
                ->exists();

        if ($yaExiste) {
            return to_route('podio.index', $parametrosRegreso)->with('error', $errorYaExiste);
        }

        $sugerencia = $this->sugerenciaPodio->sugerenciaParaCopa(
            $data['idEdicion'],
            $data['idCategoria'],
            $data['idCopa'],
        );

        if (! $sugerencia) {
            return to_route('podio.index', $parametrosRegreso)->with('error', $errorSinSugerencia);
        }

        try {
            $confirmacion = $this->confirmadorPodio->confirmar(
                $data['idEdicion'],
                $data['idCategoria'],
                $data['idCopa'],
            );
        } catch (Throwable $error) {
            Log::error('No se pudo confirmar el podio sugerido', [
                'idEdicion' => $data['idEdicion'],
                'idCategoria' => $data['idCategoria'],
                'idCopa' => $data['idCopa'],
                'exception' => $error,
            ]);

            return to_route('podio.index', $parametrosRegreso)
                ->with('error', 'No se pudo confirmar el podio. Intentá nuevamente.');
        }

        return match ($confirmacion['resultado']) {
            'confirmado' => to_route('podio.index', $parametrosRegreso)->with('status', 'Podio confirmado.'),
            'ya_existe' => to_route('podio.index', $parametrosRegreso)->with('error', $errorYaExiste),
            default => to_route('podio.index', $parametrosRegreso)->with('error', $errorSinSugerencia),
        };
    }

    /**
     * Misma resolución de contexto que el composer de Panel.admin: confía en
     * el atributo que deja PanelEdicionContextMiddleware (incluso si es
     * null) y solo recurre a la query/sesión si el middleware no corrió.
     */
    private function resolverIdEdicion(Request $request): ?int
    {
        $idEdicion = $request->attributes->has('panel.idEdicion')
            ? $request->attributes->get('panel.idEdicion')
            : ($request->query('idEdicion') ?: $request->session()->get('panel.idEdicion'));

        return $idEdicion ? (int) $idEdicion : null;
    }
}
