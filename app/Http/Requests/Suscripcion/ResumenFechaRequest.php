<?php

namespace App\Http\Requests\Suscripcion;

use Illuminate\Foundation\Http\FormRequest;

/** Fecha (y categoría opcional) del resumen o de la próxima fecha que el administrador cuenta o envía a los suscriptores. */
class ResumenFechaRequest extends FormRequest
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
            'idFecha' => ['required', 'integer', 'exists:fechas,id'],
            'idCategoria' => ['nullable', 'integer', 'exists:categorias,id'],
        ];
    }

    public function idFecha(): int
    {
        return (int) $this->validated('idFecha');
    }

    public function idCategoria(): ?int
    {
        $idCategoria = $this->validated('idCategoria');

        return $idCategoria === null ? null : (int) $idCategoria;
    }
}
