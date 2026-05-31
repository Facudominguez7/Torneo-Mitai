<?php

namespace App\Http\Requests\TablaPosicion;

use App\Models\Grupo;
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $idEdicion = $this->integer('idEdicion');
        $idCategoria = $this->integer('idCategoria');

        return [
            'idEdicion' => ['required', 'integer', Rule::exists('ediciones', 'id')],
            'idCategoria' => [
                'nullable',
                'integer',
                Rule::exists('categorias', 'id')->where(fn ($query) => $query->where('idEdicion', $idEdicion)),
            ],
            'idGrupo' => [
                'required',
                'integer',
                Rule::exists('grupos', 'id')->where(fn ($query) => $query
                    ->where('idEdicion', $idEdicion)
                    ->when($idCategoria, fn ($subQuery) => $subQuery->where('idCategoria', $idCategoria))),
            ],
            'idEquipo' => [
                'required',
                'integer',
                // La pertenencia del equipo a la edicion/categoria vive en equipo_ediciones,
                // no en los campos legacy de la tabla equipos (que conservan datos historicos).
                Rule::exists('equipo_ediciones', 'idEquipo')->where(fn ($query) => $query
                    ->where('idEdicion', $idEdicion)
                    ->when($idCategoria, fn ($subQuery) => $subQuery->where('idCategoria', $idCategoria))),
            ],
            'puntos' => ['required', 'integer', 'min:0'],
            'golesFavor' => ['required', 'integer', 'min:0'],
            'golesContra' => ['required', 'integer', 'min:0'],
            'diferenciaGoles' => ['required', 'integer'],
            'jugado' => ['required', 'integer', 'min:0'],
            'ganado' => ['required', 'integer', 'min:0'],
            'perdido' => ['required', 'integer', 'min:0'],
            'empatado' => ['required', 'integer', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validation) {
            $idCategoria = $this->integer('idCategoria');

            if (! $idCategoria) {
                return;
            }

            $grupo = Grupo::find($this->integer('idGrupo'));
            if ($grupo && (int) $grupo->idCategoria !== $idCategoria) {
                $validation->errors()->add('idGrupo', 'El grupo no pertenece a la categoría seleccionada.');
            }
        });
    }
}
