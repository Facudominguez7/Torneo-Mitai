<?php

namespace App\Http\Requests\Cobros;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DeudaManualRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * El importe acepta "$ 25.000" con puntos de miles bien ubicados.
     */
    protected function prepareForValidation(): void
    {
        $importe = $this->input('importe_ars');

        if (is_string($importe) && preg_match('/^\s*\$?\s*(?:[0-9]+|[0-9]{1,3}(?:\.[0-9]{3})+)\s*$/D', $importe)) {
            $importe = str_replace(['.', '$', ' '], '', trim($importe));
        }

        if (! $this->has('idEdicion') && $this->attributes->get('panel.idEdicion')) {
            $this->merge(['idEdicion' => $this->attributes->get('panel.idEdicion')]);
        }

        $this->merge(['importe_ars' => $importe]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idEdicion' => ['required', 'integer', 'min:1', 'exists:ediciones,id'],
            'idEquipo' => ['required', 'integer', 'min:1', 'exists:equipos,id'],
            'importe_ars' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'clave_idempotencia' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'idEquipo.required' => 'Elegí el equipo.',
            'importe_ars.required' => 'Ingresá el importe.',
            'importe_ars.integer' => 'El importe debe ser un número entero de pesos.',
            'importe_ars.min' => 'El importe debe ser mayor a 0.',
            'importe_ars.max' => 'El importe es demasiado alto.',
            'fecha.required' => 'Ingresá la fecha de la deuda.',
            'fecha.date_format' => 'La fecha no es válida.',
            'fecha.before_or_equal' => 'La fecha no puede ser posterior a hoy.',
            'motivo.max' => 'El motivo puede tener hasta 255 caracteres.',
            'clave_idempotencia.required' => 'Recargá la página y volvé a cargar la deuda.',
            'clave_idempotencia.uuid' => 'La clave de reintento no es válida.',
        ];
    }
}
