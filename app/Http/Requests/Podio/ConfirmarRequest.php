<?php

namespace App\Http\Requests\Podio;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmarRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Solo se validan los identificadores: los equipos de campeón/subcampeón
     * nunca llegan por el request, se recalculan en el servidor
     * (ControladorPodio::confirmar) para no confiar en lo que mande el cliente.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idEdicion' => 'required|integer|exists:ediciones,id',
            'idCategoria' => 'required|integer|exists:categorias,id',
            'idCopa' => 'required|integer|exists:copas,id',
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
            'idCopa.required' => 'Falta la copa.',
            'idCopa.exists' => 'La copa indicada no existe.',
        ];
    }
}
