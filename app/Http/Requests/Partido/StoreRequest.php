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
            'idFechas' => 'nullable|required_without:nombreFecha|exists:fechas,id',
            // Fecha nueva escrita en el mismo alta: se crea para la edición elegida.
            'nombreFecha' => $isStore ? 'nullable|required_without:idFechas|string|min:5|max:50' : 'nullable|string',
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
            'idFechas.required_without' => 'Elegí una fecha o escribí el nombre de una fecha nueva.',
            'nombreFecha.required_without' => 'Elegí una fecha o escribí el nombre de una fecha nueva.',
            'nombreFecha.min' => 'El nombre de la fecha debe tener al menos 5 caracteres.',
            'nombreFecha.max' => 'El nombre de la fecha no puede superar los 50 caracteres.',
            'idEquipoVisitante.different' => 'El equipo visitante debe ser distinto al equipo local.',
            'idGrupo.prohibited' => 'El grupo no puede ser modificado desde esta pantalla.',
            'idCategoria.prohibited' => 'La categoría no puede ser modificada desde esta pantalla.',
            'idEdicion.prohibited' => 'La edición no puede ser modificada desde esta pantalla.',
            'golesEquipoLocal.prohibited' => 'Los resultados no se editan desde esta pantalla.',
            'golesEquipoVisitante.prohibited' => 'Los resultados no se editan desde esta pantalla.',
        ];
    }
}
