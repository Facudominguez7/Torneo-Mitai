<?php

namespace App\Http\Requests\InstanciaFinal;

use Illuminate\Foundation\Http\FormRequest;

class MoverAFechaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'idFechas' => ['required', 'integer', 'exists:fechas,id'],
            'confirmar_interzonal' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'idFechas.required' => 'Elegí la fecha a la que pasa el partido.',
            'idFechas.integer' => 'La fecha elegida no es válida.',
            'idFechas.exists' => 'La fecha elegida no existe.',
        ];
    }
}
