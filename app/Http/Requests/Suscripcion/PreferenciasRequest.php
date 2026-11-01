<?php

namespace App\Http\Requests\Suscripcion;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Arr;

/** Avisos desde la página de preferencias: las mismas reglas de la suscripción, sin el correo. */
class PreferenciasRequest extends SuscribirRequest
{
    /**
     * @return array<string, Rule|array|string>
     */
    public function rules(): array
    {
        return Arr::except(parent::rules(), ['email']);
    }
}
