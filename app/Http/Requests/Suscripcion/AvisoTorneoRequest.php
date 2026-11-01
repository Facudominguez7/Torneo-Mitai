<?php

namespace App\Http\Requests\Suscripcion;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Aviso libre del administrador a los suscriptores. El identificador viene de la carga del
 * formulario: reenviarlo (doble clic, reintento del navegador) no manda el aviso dos veces.
 */
class AvisoTorneoRequest extends FormRequest
{
    public const TITULO_MAXIMO = 120;

    public const MENSAJE_MAXIMO = 2000;

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
            'id_envio' => ['required', 'uuid'],
            'titulo' => ['required', 'string', 'max:'.self::TITULO_MAXIMO],
            'mensaje' => ['required', 'string', 'max:'.self::MENSAJE_MAXIMO],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id_envio.required' => 'Recargá la página para preparar un aviso nuevo.',
            'id_envio.uuid' => 'Recargá la página para preparar un aviso nuevo.',
            'titulo.required' => 'Escribí el título del aviso.',
            'titulo.max' => 'El título puede tener hasta '.self::TITULO_MAXIMO.' caracteres.',
            'mensaje.required' => 'Escribí el mensaje del aviso.',
            'mensaje.max' => 'El mensaje puede tener hasta '.self::MENSAJE_MAXIMO.' caracteres.',
        ];
    }

    public function idEnvio(): string
    {
        return mb_strtolower((string) $this->validated('id_envio'));
    }

    public function titulo(): string
    {
        return (string) $this->validated('titulo');
    }

    public function mensaje(): string
    {
        return (string) $this->validated('mensaje');
    }
}
