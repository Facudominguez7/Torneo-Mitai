<?php

namespace App\Http\Requests\Premios;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GuardarGoleadorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Solo se valida la identificación del candidato: el nombre y el equipo
     * del goleador nunca llegan por el request, se resuelven en el servidor
     * (ConfirmadorPremios::guardarGoleador) a partir de la tabla de
     * goleadores para no confiar en lo que mande el cliente.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idEdicion' => 'required|integer|exists:ediciones,id',
            'idCategoria' => 'required|integer|exists:categorias,id',
            'dni_jugador' => 'required|string|max:20',
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
            'dni_jugador.required' => 'Elegí un jugador de la lista.',
        ];
    }
}
