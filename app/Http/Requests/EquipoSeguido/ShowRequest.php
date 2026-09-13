<?php

namespace App\Http\Requests\EquipoSeguido;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShowRequest extends FormRequest
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
            'nombre' => 'required|string|max:50',
            'idEdicion' => 'required|integer|exists:ediciones,id',
            // Enmienda #100: categoría opcional para desambiguar homónimos.
            'idCategoria' => 'nullable|integer|exists:categorias,id',
        ];
    }
}
