<?php

namespace App\Traits;

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\EquipoEdicion;
use App\Models\EquipoGrupo;
use App\Models\Grupo;
use Illuminate\Http\Request;

trait SeleccionarCategoriaTrait
{
    /**
     * Resolver la pantalla de selección de categoría y, cuando aplique, de grupo.
     *
     * Reglas de flujo:
     * - Usa la edición de la query o la edición activa persistida en middleware.
     * - Si no se envía categoría y existen categorías, selecciona la primera por defecto.
     * - Para tipos que dependen de grupo (`equipogrupo`, `partido`), carga
     *   únicamente grupos de la edición y categoría elegidas.
     * - Si existe una única opción válida (categoría o grupo), continúa de forma
     *   automática para reducir clicks, salvo que se fuerce selección manual.
     * - Para `equipogrupo` siempre muestra la pantalla para habilitar la
     *   asignación masiva interactiva de equipos pendientes.
     * - Si no llega `tipo` o llega inválido, usa `partido` como fallback seguro.
     */
    public function seleccionarCategoria(Request $request)
    {
        $ediciones = Edicion::all();
        $tipo = $request->query('tipo') ?: $request->query('tipo_selector') ?: 'partido';
        $requiereGrupo = $tipo === 'equipogrupo' || $tipo === 'partido';
        $forzarSeleccionManual = $request->boolean('forzarSeleccion');
        $tiposConCreate = ['partido', 'equipogrupo', 'instancia_final', 'campeon', 'subcampeon', 'goleador', 'valla'];
        $tiposPermitidos = ['partido', 'equipogrupo', 'grupos', 'instancia_final', 'campeon', 'subcampeon', 'goleador', 'valla'];

        if (! in_array($tipo, $tiposPermitidos, true)) {
            $tipo = 'partido';
        }

        $idEdicion = $request->query('idEdicion')
            ?: $request->attributes->get('panel.idEdicion')
            ?: $request->session()->get('panel.idEdicion');

        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $categorias = collect();
        $idCategoria = $request->query('idCategoria');

        if ($EdicionSeleccionada) {
            $categorias = Categoria::where('idEdicion', $EdicionSeleccionada->id)
                ->orderBy('nombreCategoria', 'desc')
                ->get();

            if (! $idCategoria && $categorias->isNotEmpty()) {
                $idCategoria = (int) $categorias->first()->id;
            }
        }

        $parametrosContexto = array_filter([
            'idFecha' => $request->query('idFecha'),
            'fecha' => $request->query('fecha'),
            'horario' => $request->query('horario'),
            'busqueda' => $request->query('busqueda'),
        ], static fn ($valor) => $valor !== null && $valor !== '');

        $parametrosBase = array_merge($parametrosContexto, [
            'idEdicion' => $idEdicion,
            'idCategoria' => $idCategoria,
        ]);

        $resumenAsignacion = null;
        $equiposPendientesPreview = collect();

        if (
            in_array($tipo, $tiposConCreate, true)
            && ! $forzarSeleccionManual
            && ! $requiereGrupo
            && $categorias->count() === 1
            && $idEdicion
            && $idCategoria
        ) {
            return to_route($tipo.'.create', $parametrosBase);
        }

        if ($requiereGrupo) {
            $grupos = Grupo::where('idEdicion', $idEdicion)
                ->where('idCategoria', $idCategoria)
                ->orderBy('nombre')
                ->get();

            if ($tipo === 'equipogrupo' && $idEdicion && $idCategoria && $grupos->isNotEmpty()) {
                $idsGrupos = $grupos->pluck('id');

                $equiposCategoria = EquipoEdicion::query()
                    ->join('equipos', 'equipo_ediciones.idEquipo', '=', 'equipos.id')
                    ->where('equipo_ediciones.idEdicion', $idEdicion)
                    ->where('equipo_ediciones.idCategoria', $idCategoria)
                    ->orderBy('equipos.nombre')
                    ->select('equipos.id', 'equipos.nombre')
                    ->get()
                    ->unique('id')
                    ->values();

                $equiposAsignadosIds = EquipoGrupo::query()
                    ->whereIn('idGrupo', $idsGrupos)
                    ->whereIn('idEquipo', $equiposCategoria->pluck('id'))
                    ->pluck('idEquipo')
                    ->unique()
                    ->values();

                $equiposPendientes = $equiposCategoria
                    ->reject(fn ($equipo) => $equiposAsignadosIds->contains($equipo->id))
                    ->values();

                $conteoPorGrupo = EquipoGrupo::query()
                    ->whereIn('idGrupo', $idsGrupos)
                    ->selectRaw('idGrupo, COUNT(*) as total')
                    ->groupBy('idGrupo')
                    ->pluck('total', 'idGrupo');

                $resumenAsignacion = [
                    'total_equipos' => $equiposCategoria->count(),
                    'ya_asignados' => $equiposAsignadosIds->count(),
                    'pendientes' => $equiposPendientes->count(),
                    'grupos' => $grupos->map(function ($grupo) use ($conteoPorGrupo) {
                        return [
                            'id' => $grupo->id,
                            'nombre' => $grupo->nombre,
                            'equipos_actuales' => (int) ($conteoPorGrupo[$grupo->id] ?? 0),
                        ];
                    })->values(),
                ];

                $equiposPendientesPreview = $equiposPendientes->take(8)->values();
            }

            if (
                in_array($tipo, $tiposConCreate, true)
                && ! $forzarSeleccionManual
                && $tipo !== 'equipogrupo'
                && $grupos->count() === 1
                && $idEdicion
                && $idCategoria
            ) {
                return to_route($tipo.'.create', array_merge($parametrosBase, [
                    'idGrupo' => $grupos->first()->id,
                ]));
            }

            return view('Panel.seleccionar-categoria', compact('tipo', 'categorias', 'ediciones', 'EdicionSeleccionada', 'grupos', 'idCategoria', 'parametrosContexto', 'resumenAsignacion', 'equiposPendientesPreview'));
        }

        return view('Panel.seleccionar-categoria', compact('tipo', 'categorias', 'ediciones', 'EdicionSeleccionada', 'idCategoria', 'parametrosContexto'));
    }
}
