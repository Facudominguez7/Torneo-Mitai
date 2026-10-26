<?php

namespace App\Http\Requests\Premios;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GuardarVallaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El equipo elegido se vuelve a verificar en el servidor contra
     * EquipoEdicion (ConfirmadorPremios::guardarValla): acá solo se valida
     * que exista como equipo. El nombre del arquero sí es un dato real que
     * carga el admin, por eso se valida (y se persiste) tal cual llega.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idEdicion' => 'required|integer|exists:ediciones,id',
            'idCategoria' => 'required|integer|exists:categorias,id',
            'idEquipo' => 'required|integer|exists:equipos,id',
            'nombre' => 'required|string|max:50',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'idEdicion.required' => 'Falta la edición.',
            'idEdicion.exists' => 'La edición indicada no existe.',
            'idCategoria.required' => 'Falta la categoría.',
            'idCategoria.exists' => 'La categoría indicada no existe.',
            'idEquipo.required' => 'Elegí un equipo de la lista.',
            'idEquipo.exists' => 'El equipo indicado no existe.',
            'nombre.required' => 'Ingresá el nombre del arquero.',
            'nombre.max' => 'El nombre del arquero no puede superar los 50 caracteres.',
        ];
    }
}
