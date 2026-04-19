<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

final class CopiarPlanillaPartidoAnteriorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'partido_id' => ['required', 'integer'],
            'idEdicion' => ['required', 'integer'],
            'tipoPartido' => ['required', 'string'],
            'horario' => ['required'],
            'scope' => ['required', 'in:local,visitante'],
            'updated_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'partido_id.required' => 'El partido es obligatorio.',
            'idEdicion.required' => 'La edición es obligatoria.',
            'tipoPartido.required' => 'El tipo de partido es obligatorio.',
            'horario.required' => 'El horario es obligatorio.',
            'scope.required' => 'Debes indicar el equipo a copiar.',
            'scope.in' => 'El equipo a copiar no es válido.',
            'updated_at.date' => 'La versión enviada no tiene un formato válido.',
        ];
    }
}
