<?php

namespace App\Http\Requests\Suscripcion;

use App\Services\ResumenFechaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResumenDiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dia' => ['required', 'date_format:Y-m-d'],
            'idCategoria' => ['nullable', 'integer', Rule::exists('categorias', 'id')->where('idEdicion', app(ResumenFechaService::class)->idUltimaEdicion())],
        ];
    }

    public function dia(): string
    {
        return $this->validated('dia');
    }

    public function idCategoria(): ?int
    {
        $categoria = $this->validated('idCategoria');

        return $categoria === null ? null : (int) $categoria;
    }
}
