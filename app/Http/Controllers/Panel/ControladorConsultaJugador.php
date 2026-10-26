<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Edicion;
use App\Services\ConsultaJugadorService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class ControladorConsultaJugador extends Controller
{
    public function __construct(private readonly ConsultaJugadorService $consultaJugador) {}

    /**
     * Mostrar el formulario de búsqueda con la edición de contexto del panel.
     *
     * Prioriza la edición solicitada por query (`idEdicion`) para que el módulo
     * sea consistente con el selector global del admin.
     */
    public function index(Request $request)
    {
        $ediciones = Edicion::orderBy('id', 'desc')->get(); // Para el layout admin.blade.php
        $idEdicionSolicitada = $request->filled('idEdicion')
            ? (int) $request->input('idEdicion')
            : null;
        $EdicionSeleccionada = $this->resolverEdicionSeleccionada($idEdicionSolicitada);
        $edicionActual = $EdicionSeleccionada;

        // Mantener el DNI si viene en la URL
        $dni = $request->input('dni', '');

        return view('Panel.jugador.consulta', compact('edicionActual', 'ediciones', 'EdicionSeleccionada', 'dni'));
    }

    /**
     * Buscar jugador por DNI respetando la edición seleccionada.
     */
    public function buscar(Request $request)
    {
        $request->validate([
            'dni' => 'required|integer',
            'idEdicion' => 'nullable|integer|exists:ediciones,id',
        ]);

        $dni = (int) $request->input('dni');
        $idEdicionSolicitada = $request->filled('idEdicion')
            ? (int) $request->input('idEdicion')
            : null;
        $edicionActual = $this->resolverEdicionSeleccionada($idEdicionSolicitada);

        if (! $edicionActual) {
            return redirect()->route('jugador.consulta')
                ->with('error', 'No hay ediciones disponibles para realizar la consulta.');
        }

        $idEdicion = $edicionActual->id;
        $consulta = $this->consultaJugador->buscar($dni, $idEdicion);

        if ($consulta === null) {
            return redirect()->route('jugador.consulta', ['dni' => $dni, 'idEdicion' => $idEdicion])
                ->with('error', 'No se encontró ningún jugador con el DNI: '.$dni);
        }

        $ediciones = Edicion::orderBy('id', 'desc')->get(); // Para el layout admin.blade.php
        $EdicionSeleccionada = $edicionActual; // Para el selector de edición en el header

        return view('Panel.jugador.consulta', array_merge($consulta, compact('edicionActual', 'ediciones', 'EdicionSeleccionada')));
    }

    /**
     * Misma búsqueda que `buscar`, devuelta como fragmento HTML para el modal
     * del buscador del header del panel.
     */
    public function consultaRapida(Request $request): Response
    {
        $validador = Validator::make($request->query(), [
            'dni' => 'required|integer|min:1',
            'idEdicion' => 'nullable|integer',
        ]);

        if ($validador->fails()) {
            return response()->view('Panel.jugador._consulta-rapida', [
                'error' => 'Ingresá un DNI válido, solo con números.',
            ], 422);
        }

        $dni = (int) $request->query('dni');
        $idEdicionSolicitada = $request->filled('idEdicion')
            ? (int) $request->query('idEdicion')
            : null;
        $edicionActual = $this->resolverEdicionSeleccionada($idEdicionSolicitada);

        if (! $edicionActual) {
            return response()->view('Panel.jugador._consulta-rapida', [
                'error' => 'No hay ediciones disponibles para realizar la consulta.',
            ]);
        }

        $consulta = $this->consultaJugador->buscar($dni, $edicionActual->id);

        return response()->view('Panel.jugador._consulta-rapida', array_merge($consulta ?? [], [
            'dni' => $dni,
            'edicionActual' => $edicionActual,
            'error' => $consulta === null ? 'No se encontró ningún jugador con el DNI: '.$dni : null,
        ]));
    }

    /**
     * Resolver edición de trabajo priorizando el contexto solicitado por el usuario.
     *
     * Mantiene compatibilidad: si no llega `idEdicion`, cae en la última edición.
     */
    private function resolverEdicionSeleccionada(?int $idEdicionSolicitada): ?Edicion
    {
        if ($idEdicionSolicitada) {
            return Edicion::find($idEdicionSolicitada) ?? Edicion::orderBy('id', 'desc')->first();
        }

        return Edicion::orderBy('id', 'desc')->first();
    }
}
