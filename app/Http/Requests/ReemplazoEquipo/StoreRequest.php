<?php

namespace App\Http\Requests\ReemplazoEquipo;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'idEdicion' => ['required', 'integer', 'exists:ediciones,id'],
            'idCategoria' => ['required', 'integer', 'exists:categorias,id'],
            'idGrupo' => ['nullable', 'integer'],
            'idEquipoReemplazado' => ['required', 'integer', 'exists:equipos,id'],
            'origen' => ['required', 'in:existente,nuevo,otra_edicion'],
            'idEquipoExistente' => ['required_if:origen,existente', 'prohibited_unless:origen,existente', 'nullable', 'integer', 'exists:equipos,id'],
            'idEquipoOtraEdicion' => ['required_if:origen,otra_edicion', 'prohibited_unless:origen,otra_edicion', 'nullable', 'integer', 'exists:equipos,id'],
            'nombre' => ['required_if:origen,nuevo', 'prohibited_unless:origen,nuevo', 'nullable', 'string', 'min:2', 'max:50'],
            'foto' => ['prohibited_unless:origen,nuevo', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'confirmar' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'origen.required' => 'Elegí si el reemplazo es un equipo inscripto, un equipo nuevo o un equipo de otra edición.',
            'origen.in' => 'Elegí si el reemplazo es un equipo inscripto, un equipo nuevo o un equipo de otra edición.',
            'idEquipoExistente.required_if' => 'Elegí el equipo que reemplaza.',
            'idEquipoExistente.exists' => 'El equipo elegido no existe.',
            'idEquipoOtraEdicion.required_if' => 'Elegí el equipo de otra edición que reemplaza.',
            'idEquipoOtraEdicion.exists' => 'El equipo elegido no existe.',
            'idEquipoOtraEdicion.prohibited_unless' => 'Elegí una sola opción para el reemplazante.',
            'idEquipoExistente.prohibited_unless' => 'Elegí una sola opción: un equipo inscripto o un equipo nuevo.',
            'nombre.required_if' => 'Escribí el nombre del equipo nuevo.',
            'nombre.min' => 'El nombre del equipo nuevo es demasiado corto.',
            'nombre.max' => 'El nombre del equipo nuevo no puede superar los 50 caracteres.',
            'nombre.prohibited_unless' => 'Elegí una sola opción: un equipo inscripto o un equipo nuevo.',
            'foto.prohibited_unless' => 'Elegí una sola opción: un equipo inscripto o un equipo nuevo.',
            'foto.mimes' => 'El escudo debe ser una imagen JPG, PNG o WEBP.',
            'foto.max' => 'El escudo no puede pesar más de 2 MB.',
            'confirmar.accepted' => 'Confirmá que entendés lo que implica el reemplazo.',
        ];
    }
}
