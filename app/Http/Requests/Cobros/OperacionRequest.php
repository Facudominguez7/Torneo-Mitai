<?php

namespace App\Http\Requests\Cobros;

use App\Models\CargoPlanilla;
use App\Models\EquipoEdicion;
use App\Services\Cobros\PagosCobroService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Ramsey\Uuid\Uuid;

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
        $pagoSimple = $operacion === 'cobros.pagos' && ! $this->esPagoCombinado();

        $arsEnteros = function (string $atributo, mixed $valor, Closure $fallar): void {
            if (! is_int($valor) && ! (is_string($valor) && ctype_digit($valor))) {
                $fallar('Ingresá ARS enteros, sin decimales.');
            }
        };

        return [
            'idEdicion' => [$configuracion ? 'nullable' : 'required', 'integer', 'exists:ediciones,id'],
            'idEquipo' => [$configuracion ? 'nullable' : 'required', 'integer', 'exists:equipos,id'],
            'cargo_id' => [in_array($operacion, ['cobros.pagos', 'cobros.exenciones'], true) ? 'required' : 'nullable', 'integer', 'exists:cargo_planillas,id'],
            'importe_ars' => [$pagoSimple ? 'required' : 'nullable', $arsEnteros, 'regex:/^[0-9]+$/D', 'integer', 'min:1', 'max:2147483647'],
            'importe_efectivo_ars' => ['nullable', $arsEnteros, 'regex:/^[0-9]+$/D', 'integer', 'min:0', 'max:2147483647'],
            'importe_mercado_pago_ars' => ['nullable', $arsEnteros, 'regex:/^[0-9]+$/D', 'integer', 'min:0', 'max:2147483647'],
            'tarifa_ars' => [$operacion === 'cobros.tarifa' ? 'required' : 'nullable', $arsEnteros, 'regex:/^[0-9]+$/D', 'integer', 'min:1', 'max:2147483647'],
            'medio' => [$pagoSimple ? 'required' : 'nullable', Rule::in(PagosCobroService::MEDIOS)],
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
            if ($this->route()->getName() === 'cobros.pagos' && $this->esPagoCombinado()) {
                $total = $this->integer('importe_efectivo_ars') + $this->integer('importe_mercado_pago_ars');
                if ($total < 1) {
                    $validator->errors()->add('importe_efectivo_ars', 'Ingresá el importe recibido en efectivo, en Mercado Pago o en ambos.');
                } elseif ($total > 2147483647) {
                    $validator->errors()->add('importe_efectivo_ars', 'El total supera el máximo permitido.');
                }
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

    /**
     * Partes del ingreso en orden ascendente de importe: el excedente, si existe,
     * queda en el último recibo. El efectivo conserva la clave del cliente y
     * Mercado Pago deriva la suya para que el reintento sea determinista.
     *
     * @return array<int, array{medio: string, importe_ars: int, clave: string}>
     */
    public function partesPago(): array
    {
        $clave = (string) $this->input('clave_idempotencia');
        if (! $this->esPagoCombinado()) {
            return [['medio' => (string) $this->input('medio'), 'importe_ars' => $this->integer('importe_ars'), 'clave' => $clave]];
        }

        $partes = array_values(array_filter([
            ['medio' => 'efectivo', 'importe_ars' => $this->integer('importe_efectivo_ars'), 'clave' => $clave],
            ['medio' => 'mercado_pago', 'importe_ars' => $this->integer('importe_mercado_pago_ars'), 'clave' => $clave],
        ], fn (array $parte): bool => $parte['importe_ars'] > 0));
        if (count($partes) === 2) {
            $partes[1]['clave'] = Uuid::uuid5(strtolower($clave), 'mercado_pago')->toString();
        }
        usort($partes, fn (array $a, array $b): int => $a['importe_ars'] <=> $b['importe_ars']);

        return $partes;
    }

    private function esPagoCombinado(): bool
    {
        return ! $this->filled('importe_ars') && ! $this->filled('medio');
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
