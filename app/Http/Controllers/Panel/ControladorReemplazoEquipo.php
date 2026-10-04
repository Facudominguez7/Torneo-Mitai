<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReemplazoEquipo\StoreRequest;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Services\ReemplazadorEquipo;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ControladorReemplazoEquipo extends Controller
{
    public function __construct(private readonly ReemplazadorEquipo $reemplazador) {}

    /**
     * Pantalla para reemplazar un equipo, con lo que heredaría el reemplazante.
     */
    public function create(Request $request): View
    {
        $idEdicion = $request->integer('idEdicion');
        $idCategoria = $request->integer('idCategoria');
        $idEquipo = $request->integer('idEquipo');

        $edicion = Edicion::findOrFail($idEdicion);
        $categoria = Categoria::query()->where('idEdicion', $idEdicion)->findOrFail($idCategoria);
        $equipoReemplazado = Equipo::findOrFail($idEquipo);

        abort_unless(
            EquipoEdicion::query()->where('idEquipo', $idEquipo)->where('idEdicion', $idEdicion)->where('idCategoria', $idCategoria)->exists(),
            404,
        );

        $vistaPrevia = $this->reemplazador->vistaPrevia($idEdicion, $idCategoria, $idEquipo);
        $motivoImposibilidad = $this->reemplazador->motivoImposibilidad($idEdicion, $idCategoria, $idEquipo);
        $equiposElegibles = $motivoImposibilidad === null
            ? $this->reemplazador->equiposElegibles($idEdicion, $idCategoria, $idEquipo)
            : collect();

        $ediciones = Edicion::all();
        $EdicionSeleccionada = $edicion;
        $idGrupo = $request->input('idGrupo', $vistaPrevia['grupo']?->id);

        return view('Panel.reemplazo-equipo.create', compact(
            'ediciones',
            'EdicionSeleccionada',
            'categoria',
            'equipoReemplazado',
            'vistaPrevia',
            'motivoImposibilidad',
            'equiposElegibles',
            'idEdicion',
            'idCategoria',
            'idGrupo',
        ));
    }

    /**
     * Ejecuta el reemplazo y vuelve a la tabla de posiciones del mismo contexto.
     */
    public function store(StoreRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $idEdicion = (int) $datos['idEdicion'];
        $idCategoria = (int) $datos['idCategoria'];
        $idEquipoReemplazado = (int) $datos['idEquipoReemplazado'];
        $esNuevo = $datos['origen'] === 'nuevo';

        $escudo = $esNuevo ? $this->guardarEscudo($request) : null;

        try {
            $reemplazo = $this->reemplazador->reemplazar(
                $idEdicion,
                $idCategoria,
                $idEquipoReemplazado,
                $esNuevo ? null : (int) $datos['idEquipoExistente'],
                $esNuevo ? ['nombre' => $datos['nombre'], 'foto' => $escudo] : null,
            );
        } catch (Throwable $error) {
            // Si el reemplazo no se hizo, el escudo subido no queda huérfano en disco.
            if ($escudo !== null) {
                File::delete(public_path('../public_html/fotos/equipos/'.$escudo));
            }

            if ($error instanceof DomainException) {
                return back()->withInput()->with('error', $error->getMessage());
            }

            throw $error;
        }

        $nuevo = Equipo::findOrFail($reemplazo->idEquipoReemplazante);
        $viejo = Equipo::findOrFail($reemplazo->idEquipoReemplazado);

        return to_route('tabla_posicion.index', array_filter([
            'idEdicion' => $idEdicion,
            'idCategoria' => $idCategoria,
            'idGrupo' => $datos['idGrupo'] ?? null,
        ], static fn ($valor) => $valor !== null && $valor !== ''))
            ->with('success', "{$nuevo->nombre} reemplazó a {$viejo->nombre} y heredó su posición.");
    }

    /**
     * Guarda el escudo igual que ControladorEquipo::store y devuelve el nombre del archivo.
     */
    private function guardarEscudo(StoreRequest $request): ?string
    {
        if (! $request->hasFile('foto') || ! $request->file('foto')->isValid()) {
            return null;
        }

        // Sufijo aleatorio: dos subidas en el mismo segundo no se pisan ni se borran entre sí.
        $nombreArchivo = time().'_'.Str::random(8).'.'.$request->file('foto')->getClientOriginalExtension();
        $request->file('foto')->move(public_path('../public_html/fotos/equipos'), $nombreArchivo);

        return $nombreArchivo;
    }
}
