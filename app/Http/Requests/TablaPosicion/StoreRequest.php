<?php

namespace App\Http\Requests\TablaPosicion;

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

        return [
            'idEdicion' => ['required', 'integer', Rule::exists('ediciones', 'id')],
            'idGrupo' => [
                'required',
                'integer',
                Rule::exists('grupos', 'id')->where(fn ($query) => $query->where('idEdicion', $idEdicion)),
            ],
            'idEquipo' => [
                'required',
                'integer',
                Rule::exists('equipos', 'id')->where(fn ($query) => $query->where('idEdicion', $idEdicion)),
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
}
