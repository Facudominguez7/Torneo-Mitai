<?php

namespace App\Traits;

use App\Models\Categoria;
use App\Models\Edicion;
use Illuminate\Http\Request;

trait SeleccionarEdicionTrait
{
    public function seleccionarEdicion(Request $request)
    {
        $idEdicionDestino = $request->idEdicion; // edición donde se agregará el equipo
        $EdicionSeleccionada = $idEdicionDestino ? Edicion::find($idEdicionDestino) : null;
        $ediciones = Edicion::select('id','nombre')->orderByDesc('id')->get();
        $tipo = $request->tipo;

        $edicionOrigenId = $request->edicionfiltro; // edición desde la que se copiará
        $categorias = $edicionOrigenId
            ? Categoria::where('idEdicion', $edicionOrigenId)->orderBy('nombreCategoria')->get()
            : collect();

        return view('Panel.seleccionar-edicion', [
            'ediciones' => $ediciones,
            'EdicionSeleccionada' => $EdicionSeleccionada,
            'tipo' => $tipo,
            'categorias' => $categorias,
        ]);
    }
}