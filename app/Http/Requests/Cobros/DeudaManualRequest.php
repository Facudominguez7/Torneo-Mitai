<?php

namespace App\Http\Requests\Cobros;

use App\Models\CargoPlanilla;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeudaManualRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * El partido llega como "tipo-id" desde la lista de partidos jugados.
     */
    protected function prepareForValidation(): void
    {
        $partido = (string) $this->input('partido', '');
        $separador = strrpos($partido, '-');
        $importe = $this->input('importe_ars');

        if (is_string($importe) && preg_match('/^\s*\$?\s*(?:[0-9]+|[0-9]{1,3}(?:\.[0-9]{3})+)\s*$/D', $importe)) {
            $importe = str_replace(['.', '$', ' '], '', trim($importe));
        }

        if (! $this->has('idEdicion') && $this->attributes->get('panel.idEdicion')) {
            $this->merge(['idEdicion' => $this->attributes->get('panel.idEdicion')]);
        }

        $this->merge([
            'tipo' => $separador === false ? null : substr($partido, 0, $separador),
            'partido_id' => $separador === false ? null : substr($partido, $separador + 1),
            'importe_ars' => $importe,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idEdicion' => ['required', 'integer', 'min:1', 'exists:ediciones,id'],
            'idEquipo' => ['required', 'integer', 'min:1', 'exists:equipos,id'],
            'partido' => ['required', 'string'],
            'tipo' => ['required', Rule::in(array_keys(CargoPlanilla::TIPOS_FINANCIEROS))],
            'partido_id' => ['required', 'integer', 'min:1'],
            'importe_ars' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'idEquipo.required' => 'Elegí el equipo.',
            'partido.required' => 'Elegí el partido adeudado.',
            'tipo.required' => 'Elegí el partido adeudado.',
            'tipo.in' => 'Elegí un partido de la lista.',
            'partido_id.required' => 'Elegí el partido adeudado.',
            'partido_id.integer' => 'Elegí un partido de la lista.',
            'importe_ars.required' => 'Ingresá el importe.',
            'importe_ars.integer' => 'El importe debe ser un número entero de pesos.',
            'importe_ars.min' => 'El importe debe ser mayor a 0.',
            'importe_ars.max' => 'El importe es demasiado alto.',
            'motivo.max' => 'El motivo puede tener hasta 255 caracteres.',
        ];
    }
}
