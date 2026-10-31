<?php

namespace App\Http\Requests\Categoria;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombreCategoria' => 'required|min:5|max:50',
            'idEdicion' => 'required|integer',
            'jugadores_maximos' => 'nullable|integer|min:1|max:100',
            'anio_desde' => 'nullable|integer|min:2000|max:2099|required_with:anio_hasta',
            'anio_hasta' => 'nullable|integer|min:2000|max:2099|gte:anio_desde',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'anio_desde.required_with' => 'Completá el año desde para cargar el año hasta.',
            'anio_hasta.gte' => 'El año hasta no puede ser anterior al año desde.',
        ];
    }
}
