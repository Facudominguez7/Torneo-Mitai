<?php

namespace App\Http\Requests\Suscripcion;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Arr;

/** Avisos y equipos desde la página de preferencias: las mismas reglas de la suscripción, sin el correo. */
class PreferenciasRequest extends SuscribirRequest
{
    protected function prepareForValidation(): void
    {
        // El selector "Sumar un equipo" sin elegir manda un valor vacío.
        if (is_array($this->input('equipos'))) {
            $this->merge(['equipos' => array_values(array_filter($this->input('equipos'), fn (mixed $id): bool => $id !== null && $id !== ''))]);
        }
    }

    /**
     * @return array<string, Rule|array|string>
     */
    public function rules(): array
    {
        return Arr::except(parent::rules(), ['email']);
    }
}
