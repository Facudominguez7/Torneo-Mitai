<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\EquipoEdicion\StoreRequest;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Traits\SeleccionarEdicionTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ControladorEquiposEdiciones extends Controller
{
    use SeleccionarEdicionTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $idCategoria = $request->idCategoria; // categoría a copiar
        $idEdicion = $request->idEdicion; // edición destino
        $edicionfiltro = $request->edicionfiltro; // edición origen
        $idEquipoSel = $request->idEquipo; // opcional, desde buscador global

        $ediciones = Edicion::all();
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null; // destino
        $CategoriaSeleccionada = $idCategoria ? Categoria::find($idCategoria) : null;

        if ($idCategoria && $idEdicion && $edicionfiltro) {
            // Si viene un equipo seleccionado, verificar si YA pertenece a la edición y categoría destino
            if ($idEquipoSel) {
                $equipoSel = Equipo::with('categoria')->find($idEquipoSel);
                if ($equipoSel && $equipoSel->categoria) {
                    $nombreCategoriaOrigen = $equipoSel->categoria->nombreCategoria;
                    $normalizar = function (string $texto) {
                        $texto = trim($texto);
                        $texto = preg_replace('/\s+/', ' ', $texto);
                        $lower = mb_strtolower($texto, 'UTF-8');
                        $reemplazos = [
                            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
                            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
                            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
                            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
                            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
                            'ñ' => 'n',
                        ];

                        return strtr($lower, $reemplazos);
                    };
                    $normalizadoOrigen = $normalizar($nombreCategoriaOrigen);
                    $categoriaDestinoEq = Categoria::where('idEdicion', (int) $idEdicion)->get()->first(function ($cat) use ($normalizar, $normalizadoOrigen) {
                        return $normalizar($cat->nombreCategoria) === $normalizadoOrigen;
                    });
                    if ($categoriaDestinoEq) {
                        $dup = EquipoEdicion::where('idEquipo', $equipoSel->id)
                            ->where('idEdicion', (int) $idEdicion)
                            ->where('idCategoria', $categoriaDestinoEq->id)
                            ->exists();
                        if ($dup) {
                            return redirect()->action([ControladorEquipo::class, 'index'], ['idEdicion' => (int) $idEdicion])
                                ->with('status', 'Este equipo ya pertenece a la edición y categoría seleccionadas.');
                        }
                    }
                }
            }
            $equipos = Equipo::where('idCategoria', $idCategoria)
                ->where('idEdicion', $edicionfiltro)
                ->whereDoesntHave('equipoEdiciones', function ($q) use ($idEdicion) {
                    $q->where('idEdicion', $idEdicion);
                })
                ->select('id', 'nombre')
                ->orderBy('nombre')
                ->get();
        } else {
            $equipos = collect();
        }
        $equipoedicion = new EquipoEdicion;

        return view('Panel.equipoedicion.create', compact('equipos', 'equipoedicion', 'ediciones', 'EdicionSeleccionada', 'CategoriaSeleccionada'));
    }

    /**
     * Buscar equipos por nombre en todas las ediciones (prioriza idEdicion DESC)
     */
    public function buscarEquipos(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $limit = (int) ($request->get('limit', 20));
        $limit = $limit > 0 && $limit <= 100 ? $limit : 20;

        if (mb_strlen($q) < 2) {
            return response()->json(['items' => []]);
        }

        // Búsqueda básica por LIKE; ordena por idEdicion DESC y luego por nombre
        $items = Equipo::query()
            ->join('categorias', 'equipos.idCategoria', '=', 'categorias.id')
            ->join('ediciones', 'equipos.idEdicion', '=', 'ediciones.id')
            ->where('equipos.nombre', 'like', '%'.$q.'%')
            ->orderByDesc('equipos.idEdicion')
            ->orderBy('equipos.nombre')
            ->limit($limit)
            ->get([
                'equipos.id as idEquipo',
                'equipos.nombre as nombreEquipo',
                'equipos.idEdicion as idEdicion',
                'ediciones.nombre as nombreEdicion',
                'categorias.id as idCategoria',
                'categorias.nombreCategoria as nombreCategoria',
            ]);

        return response()->json(['items' => $items]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * Si la categoría equivalente no existe en la edición destino,
     * se crea automáticamente para simplificar el alta desde ediciones anteriores.
     */
    public function store(StoreRequest $request)
    {
        $equipo = Equipo::with('categoria')->findOrFail($request->idEquipo);
        $edicionDestinoId = (int) $request->idEdicion;

        if (! $equipo->categoria) {
            return back()->withErrors(['idEquipo' => 'El equipo no tiene categoría asociada en la edición origen.'])->withInput();
        }

        $nombreCategoriaOrigen = $equipo->categoria->nombreCategoria;

        // Función de normalización (acentos + mayúsculas + espacios)
        $normalizar = function (string $texto) {
            $texto = trim($texto);
            $texto = preg_replace('/\s+/', ' ', $texto); // espacios múltiples
            $lower = mb_strtolower($texto, 'UTF-8');
            $reemplazos = [
                'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
                'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
                'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
                'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
                'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
                'ñ' => 'n',
            ];
            $lower = strtr($lower, $reemplazos);

            return $lower;
        };

        $normalizadoOrigen = $normalizar($nombreCategoriaOrigen);

        // Traer categorías de la edición destino y buscar por nombre normalizado
        $categoriaDestino = Categoria::where('idEdicion', $edicionDestinoId)
            ->get()
            ->first(function ($cat) use ($normalizar, $normalizadoOrigen) {
                return $normalizar($cat->nombreCategoria) === $normalizadoOrigen;
            });

        if (! $categoriaDestino) {
            $categoriaDestino = Categoria::create([
                'nombreCategoria' => $nombreCategoriaOrigen,
                'idEdicion' => $edicionDestinoId,
                'anio_desde' => $equipo->categoria->anio_desde,
                'anio_hasta' => $equipo->categoria->anio_hasta,
            ]);
        }

        // Evitar duplicado exacto: mismo equipo + misma edición + misma categoría destino
        $yaExisteMismaCategoria = EquipoEdicion::where('idEquipo', $equipo->id)
            ->where('idEdicion', $edicionDestinoId)
            ->where('idCategoria', $categoriaDestino->id)
            ->exists();
        if ($yaExisteMismaCategoria) {
            return back()->withErrors([
                'idEquipo' => 'Este equipo ya pertenece a la edición y categoría seleccionadas.',
            ])->withInput();
        }

        DB::transaction(function () use ($equipo, $edicionDestinoId, $categoriaDestino) {
            EquipoEdicion::create([
                'idEquipo' => $equipo->id,
                'idEdicion' => $edicionDestinoId,
                'idCategoria' => $categoriaDestino->id,
                'golesContra' => 0,
            ]);
        });

        return redirect()->action([ControladorEquipo::class, 'index'], ['idEdicion' => $edicionDestinoId])
            ->with('status', 'Equipo asociado a la nueva edición correctamente');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, EquipoEdicion $equipoedicion)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $equipos = Equipo::whereHas('equipoEdiciones', function ($query) use ($idEdicion) {
            $query->where('idEdicion', $idEdicion);
        })->get();

        return view('Panel.equipoedicion.edit', compact('equipos', 'equipoedicion', 'ediciones', 'EdicionSeleccionada'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreRequest $request, EquipoEdicion $equipoedicion)
    {
        $equipoedicion->update($request->validated());

        return redirect()->action([ControladorEquipo::class, 'show'], ['idEdicion' => $request->idEdicion])
            ->with('status', 'Equipo actualizado correctamente');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Equipo $equipo)
    {
        return to_route('equipo.index', ['idEdicion' => $request->idEdicion])->with('status', 'Equipo Eliminado');
    }
}
