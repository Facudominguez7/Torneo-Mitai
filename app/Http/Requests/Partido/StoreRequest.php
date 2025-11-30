<?php

namespace App\Http\Requests\Partido;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Cambia a false si necesitas autorización específica
    }

    public function rules()
    {
        $isStore = $this->isMethod('post');

        return [
            'idFechas' => 'required|exists:fechas,id',
            'idEquipoLocal' => 'required|exists:equipos,id|different:idEquipoVisitante',
            'idEquipoVisitante' => 'required|exists:equipos,id|different:idEquipoLocal',
            // En creación son requeridos; en edición no se permiten
            'idGrupo' => $isStore ? 'required|exists:grupos,id' : 'prohibited',
            'idEdicion' => $isStore ? 'required|exists:ediciones,id' : 'prohibited',
            'idCategoria' => $isStore ? 'required|integer' : 'prohibited',
            // Resultados: permitir en creación (por si se precarga) y prohibir en edición por este formulario
            'golesEquipoLocal' => $isStore ? 'nullable|integer|min:0' : 'prohibited',
            'golesEquipoVisitante' => $isStore ? 'nullable|integer|min:0' : 'prohibited',
            'horario_datetime' => 'required|date',
            'cancha' => 'required|string',
            'jugado' => 'nullable|integer',
        ];
    }

    public function messages()
    {
        return [
            'idEquipoLocal.different' => 'El equipo local debe ser distinto al equipo visitante.',
            'idEquipoVisitante.different' => 'El equipo visitante debe ser distinto al equipo local.',
            'idGrupo.prohibited' => 'El grupo no puede ser modificado desde esta pantalla.',
            'idCategoria.prohibited' => 'La categoría no puede ser modificada desde esta pantalla.',
            'idEdicion.prohibited' => 'La edición no puede ser modificada desde esta pantalla.',
            'golesEquipoLocal.prohibited' => 'Los resultados no se editan desde esta pantalla.',
            'golesEquipoVisitante.prohibited' => 'Los resultados no se editan desde esta pantalla.',
        ];
    }
}
