<?php

namespace App\Http\Requests\Cobros;

use App\Models\CargoPlanilla;
use App\Models\PagoPlanilla;
use App\Services\Cobros\PagosCobroService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Corregir importe y medio de un recibo ya registrado. Además de validar campos,
 * verifica que el pago pertenezca al cargo, equipo y edición indicados para que
 * ningún identificador manipulado permita editar la cuenta de otro equipo.
 */
class EditarPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        // El motivo con texto es un registro literal: no se recortan sus espacios internos.
        $original = [];
        if ($this->isJson()) {
            $original = json_decode($this->getContent(), true) ?? [];
        } elseif ($this->isMethod('POST')) {
            parse_str($this->getContent(), $original);
        }
        if (is_array($original) && array_key_exists('motivo', $original)) {
            // Un motivo vacío o solo con espacios equivale a no indicarlo; el texto se
            // conserva literal, sin recortar sus espacios internos.
            $motivo = $original['motivo'];
            $this->merge(['motivo' => is_string($motivo) && trim($motivo) === '' ? null : $motivo]);
        }
    }

    public function rules(): array
    {
        $arsEnteros = function (string $atributo, mixed $valor, Closure $fallar): void {
            if (! is_int($valor) && ! (is_string($valor) && ctype_digit($valor))) {
                $fallar('Ingresá ARS enteros, sin decimales.');
            }
        };

        return [
            'idEdicion' => ['required', 'integer', 'exists:ediciones,id'],
            'idEquipo' => ['required', 'integer', 'exists:equipos,id'],
            'cargo_id' => ['required', 'integer', 'exists:cargo_planillas,id'],
            'pago_id' => ['required', 'integer', 'min:1', 'exists:pago_planillas,id'],
            'importe_ars' => ['required', $arsEnteros, 'regex:/^[0-9]+$/D', 'integer', 'min:1', 'max:2147483647'],
            'medio' => ['required', Rule::in(PagosCobroService::MEDIOS)],
            'motivo' => ['nullable', 'string', 'max:2000'],
            'clave_idempotencia' => ['required', 'uuid'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $cargo = CargoPlanilla::query()->whereKey($this->integer('cargo_id'))
                ->where('idEquipo', $this->integer('idEquipo'))->where('idEdicion', $this->integer('idEdicion'))->first();
            if ($cargo === null) {
                $validator->errors()->add('cargo_id', 'El cargo no pertenece al equipo y edición indicados.');

                return;
            }
            $pago = PagoPlanilla::query()->with('cuenta')->find($this->integer('pago_id'));
            if ($pago?->cuenta === null
                || (int) $pago->cuenta->idEquipo !== $this->integer('idEquipo')
                || (int) $pago->cuenta->idEdicion !== $this->integer('idEdicion')
                || (int) $pago->cargo_origen_id !== (int) $cargo->id) {
                $validator->errors()->add('pago_id', 'El pago no pertenece al cargo, equipo y edición indicados.');
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
        ];
    }
}
