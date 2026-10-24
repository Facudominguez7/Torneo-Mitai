<?php

namespace App\Http\Requests\Cobros;

use App\Models\CargoPlanilla;
use App\Models\EquipoEdicion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ConsultaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('idEdicion') && $this->attributes->get('panel.idEdicion')) {
            $this->merge(['idEdicion' => $this->attributes->get('panel.idEdicion')]);
        }
    }

    public function rules(): array
    {
        return [
            'idEdicion' => ['nullable', 'integer', 'min:1', 'exists:ediciones,id'],
            'idCategoria' => ['nullable', 'integer', Rule::exists('categorias', 'id')->where('idEdicion', $this->input('idEdicion'))],
            'idEquipo' => ['nullable', 'integer', 'exists:equipos,id'],
            'idFecha' => ['nullable', 'integer', Rule::exists('fechas', 'id')->where('idEdicion', $this->input('idEdicion'))],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'recibido_desde' => ['nullable', 'date_format:Y-m-d'],
            'recibido_hasta' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('recibido_desde'), ['after_or_equal:recibido_desde'])],
            'estado' => ['nullable', Rule::in(['deudores', 'todos', 'pendiente', 'parcial', 'pagado', 'exento'])],
            'periodo' => ['nullable', Rule::in(['hasta_hoy', 'futuros', 'todos'])],
            'tipo' => ['nullable', Rule::in(array_keys(CargoPlanilla::TIPOS_FINANCIEROS))],
            'partido' => ['nullable', 'integer', 'min:1', 'required_with:tipo'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if ($this->filled('idEquipo') && ! EquipoEdicion::query()->where('idEquipo', $this->integer('idEquipo'))->where('idEdicion', $this->integer('idEdicion'))->exists()
                && ! CargoPlanilla::query()->where('idEquipo', $this->integer('idEquipo'))->where('idEdicion', $this->integer('idEdicion'))->exists()) {
                $validator->errors()->add('idEquipo', 'El equipo no pertenece a la edición seleccionada.');
            }
            if ($this->filled('partido') && (! $this->filled('tipo') || ! CargoPlanilla::query()
                ->where('idEdicion', $this->integer('idEdicion'))->where('tipo', $this->input('tipo'))->where('parent_id', $this->integer('partido'))->exists())) {
                $validator->errors()->add('partido', 'El partido no tiene cargos en la edición seleccionada.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            '*.integer' => 'El identificador debe ser un número entero.',
            '*.exists' => 'La selección no pertenece al contexto solicitado.',
            '*.in' => 'La opción seleccionada no es válida.',
            '*.date_format' => 'Usá una fecha válida con formato AAAA-MM-DD.',
            'recibido_hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'partido.required_with' => 'Seleccioná también el partido.',
        ];
    }
}
