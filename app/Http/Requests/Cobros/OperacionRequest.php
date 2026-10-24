<?php

namespace App\Http\Requests\Cobros;

use App\Models\CargoPlanilla;
use App\Models\EquipoEdicion;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OperacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        // El motivo es un registro literal: no se normalizan sus espacios.
        $original = [];
        if ($this->isJson()) {
            $original = json_decode($this->getContent(), true) ?? [];
        } elseif ($this->isMethod('POST')) {
            parse_str($this->getContent(), $original);
        }
        if (is_array($original) && array_key_exists('motivo', $original)) {
            $this->merge(['motivo' => $original['motivo']]);
        }
    }

    public function rules(): array
    {
        $operacion = $this->route()->getName();
        $configuracion = in_array($operacion, ['cobros.tarifa', 'cobros.activar'], true);

        $arsEnteros = function (string $atributo, mixed $valor, Closure $fallar): void {
            if (! is_int($valor) && ! (is_string($valor) && ctype_digit($valor))) {
                $fallar('Ingresá ARS enteros, sin decimales.');
            }
        };

        return [
            'idEdicion' => [$configuracion ? 'nullable' : 'required', 'integer', 'exists:ediciones,id'],
            'idEquipo' => [$configuracion ? 'nullable' : 'required', 'integer', 'exists:equipos,id'],
            'cargo_id' => [in_array($operacion, ['cobros.pagos', 'cobros.exenciones'], true) ? 'required' : 'nullable', 'integer', 'exists:cargo_planillas,id'],
            'importe_ars' => [$operacion === 'cobros.pagos' ? 'required' : 'nullable', $arsEnteros, 'regex:/^[0-9]+$/D', 'integer', 'min:1', 'max:2147483647'],
            'tarifa_ars' => [$operacion === 'cobros.tarifa' ? 'required' : 'nullable', $arsEnteros, 'regex:/^[0-9]+$/D', 'integer', 'min:1', 'max:2147483647'],
            'medio' => [$operacion === 'cobros.pagos' ? 'required' : 'nullable', Rule::in(['efectivo', 'transferencia', 'mercado_pago'])],
            'clave_idempotencia' => [$operacion === 'cobros.pagos' ? 'required' : 'nullable', 'uuid'],
            'motivo' => ['nullable', 'string', 'max:2000'],
            'confirmar' => $operacion === 'cobros.activar' ? ['required', 'accepted'] : ['exclude'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if ($this->filled('cargo_id') && ! CargoPlanilla::query()->whereKey($this->integer('cargo_id'))
                ->where('idEquipo', $this->integer('idEquipo'))->where('idEdicion', $this->integer('idEdicion'))->exists()) {
                $validator->errors()->add('cargo_id', 'El cargo no pertenece al equipo y edición indicados.');
            }
            if ($this->filled('idEquipo') && ! EquipoEdicion::query()->where('idEquipo', $this->integer('idEquipo'))
                ->where('idEdicion', $this->integer('idEdicion'))->exists()) {
                $validator->errors()->add('idEquipo', 'El equipo no está inscrito en esta edición.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            '*.required' => 'Completá este campo.', '*.integer' => 'Ingresá un entero válido.',
            '*.regex' => 'Ingresá ARS enteros, sin decimales.', '*.min' => 'El importe debe ser positivo.',
            '*.max' => 'El valor supera el máximo permitido.', '*.exists' => 'La selección no existe.',
            '*.uuid' => 'La clave de reintento no es válida.', '*.in' => 'Seleccioná una opción válida.',
            'confirmar.accepted' => 'Confirmá las consecuencias de la activación.',
        ];
    }
}
