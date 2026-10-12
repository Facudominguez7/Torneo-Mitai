<?php

namespace App\Http\Requests\Edicion;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $edicionId = $this->route('edicion')?->id ?? $this->route('edicion');

        return [
            'nombre' => [
                'required',
                'string',
                'min:5',
                'max:50',
                Rule::unique('ediciones', 'nombre')->ignore($edicionId),
            ],
            'jugadores_por_planilla' => 'required|integer|min:1|max:40',
        ];
    }
}
