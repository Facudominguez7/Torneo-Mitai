<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cobros\ConsultaRequest;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Fecha;
use App\Services\Cobros\ConsultaCobrosService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;

class ControladorCobros extends Controller
{
    public function index(ConsultaRequest $request, ConsultaCobrosService $consulta): View
    {
        $datos = $this->datos($request, $consulta);
        $filas = $datos['cargos'];
        $pagina = $request->integer('page', 1);
        $datos['cargos'] = new LengthAwarePaginator($filas->forPage($pagina, 25), $filas->count(), 25, $pagina, [
            'path' => $request->url(), 'query' => $request->query(),
        ]);

        return view('Panel.cobros.index', $datos);
    }

    public function imprimir(ConsultaRequest $request, ConsultaCobrosService $consulta): View
    {
        return view('Panel.cobros.imprimir', $this->datos($request, $consulta));
    }

    private function datos(ConsultaRequest $request, ConsultaCobrosService $consulta): array
    {
        $filtros = array_filter($request->validated(), fn (mixed $valor): bool => $valor !== null && $valor !== '');
        $idEdicion = $filtros['idEdicion'] ?? null;

        return $consulta->consultar($filtros) + [
            'filtros' => $filtros,
            'EdicionSeleccionada' => Edicion::query()->find($idEdicion),
            'categorias' => Categoria::query()->where('idEdicion', $idEdicion)->orderBy('nombreCategoria')->get(),
            'equipos' => Equipo::query()->whereHas('ediciones', fn ($query) => $query->where('ediciones.id', $idEdicion))->orderBy('nombre')->get(),
            'fechas' => Fecha::query()->where('idEdicion', $idEdicion)->orderBy('id')->get(),
        ];
    }
}
