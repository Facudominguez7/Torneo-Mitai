<?php

namespace App\Http\Requests\InstanciaFinal;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Cambia a false si necesitas autorización específica
    }

    public function rules()
    {
        return [
            'idCategoria' => 'required|exists:categorias,id',
            'idCopa' => 'nullable|exists:copas,id',
            'idEdicion' => 'required|exists:ediciones,id',
            'idEquipoLocal' => 'required|exists:equipos,id',
            'idEquipoVisitante' => 'required|exists:equipos,id',
            'idFase' => 'required|exists:fases,id',
            'horario' => 'required|date_format:Y-m-d\TH:i',
            'cancha' => 'required|integer',
        ];
    }
}
