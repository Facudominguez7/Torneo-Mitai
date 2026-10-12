<?php

namespace App\Http\Requests\EquipoGrupo;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AsignacionMasivaRequest extends FormRequest
{
    /**
     * Define si el usuario autenticado puede ejecutar la asignación masiva.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas mínimas para ejecutar la distribución automática.
     *
     * Se exige edición y categoría válidas para evitar asignaciones
     * fuera de contexto por llamadas manuales al endpoint.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idEdicion' => 'required|integer|exists:ediciones,id',
            'idCategoria' => 'required|integer|exists:categorias,id',
        ];
    }
}
