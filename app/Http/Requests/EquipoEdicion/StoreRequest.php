<?php

namespace App\Http\Requests\EquipoEdicion;

use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idEquipo' => ['required', 'integer', 'exists:equipos,id'],
            'idEdicion' => ['required', 'integer', 'exists:ediciones,id'], // destino
            'edicionfiltro' => ['nullable', 'integer', 'exists:ediciones,id'], // origen
            'idCategoria' => ['nullable', 'integer', 'exists:categorias,id'],
            'golesContra' => ['nullable', 'integer'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $idEquipo = $this->input('idEquipo');
            $idEdicionDestino = $this->input('idEdicion');
            if (! $idEquipo || ! $idEdicionDestino) {
                return;
            }
            $equipo = Equipo::find($idEquipo);
            if (! $equipo) {
                $v->errors()->add('idEquipo', 'Equipo no encontrado.');

                return;
            }
            // Evitar duplicado exacto por (equipo, edición, categoría destino)
            // 1) Determinar nombre de categoría origen del equipo
            $nombreCategoriaOrigen = optional($equipo->categoria)->nombreCategoria;
            if (! $nombreCategoriaOrigen) {
                $v->errors()->add('idEquipo', 'El equipo no tiene categoría asociada en la edición origen.');

                return;
            }
            // 2) Normalizar y hallar categoría destino equivalente en la edición destino
            $normalizar = function (string $texto) {
                $texto = trim($texto);
                $texto = preg_replace('/\s+/', ' ', $texto);
                $lower = mb_strtolower($texto, 'UTF-8');
                $reemplazos = [
                    'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
                    'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
                    'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
                    'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
                    'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
                    'ñ' => 'n',
                ];

                return strtr($lower, $reemplazos);
            };
            $normalizadoOrigen = $normalizar($nombreCategoriaOrigen);
            $categoriaDestino = Categoria::where('idEdicion', (int) $idEdicionDestino)->get()->first(function ($cat) use ($normalizar, $normalizadoOrigen) {
                return $normalizar($cat->nombreCategoria) === $normalizadoOrigen;
            });
            if ($categoriaDestino) {
                $yaExiste = EquipoEdicion::where('idEquipo', $equipo->id)
                    ->where('idEdicion', (int) $idEdicionDestino)
                    ->where('idCategoria', $categoriaDestino->id)
                    ->exists();
                if ($yaExiste) {
                    $v->errors()->add('idEquipo', 'Este equipo ya pertenece a la edición y categoría seleccionadas.');
                }
            }
            // Si se envía idCategoria validar correspondencia
            if ($this->filled('idCategoria') && (int) $equipo->idCategoria !== (int) $this->input('idCategoria')) {
                $v->errors()->add('idCategoria', 'El equipo no pertenece a la categoría seleccionada.');
            }
            if ($this->filled('edicionfiltro') && (int) $equipo->idEdicion !== (int) $this->input('edicionfiltro')) {
                $v->errors()->add('edicionfiltro', 'El equipo no pertenece a la edición origen indicada.');
            }
        });
    }
}
