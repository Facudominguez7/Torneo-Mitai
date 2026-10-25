<?php

namespace App\Http\Requests\Suscripcion;

use App\Services\EmailDomainGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SuscribirRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (EmailDomainGuard::isBlocked((string) $value)) {
                        $fail(EmailDomainGuard::validationMessage());
                    }
                },
            ],
            'equipos' => ['sometimes', 'array', 'max:'.config('suscripciones.max_equipos')],
            'equipos.*' => ['integer', 'distinct', Rule::exists('equipos', 'id')],
            'avisos' => ['sometimes', 'array'],
            'avisos.*' => ['string', Rule::in(array_keys(config('suscripciones.avisos')))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Escribí tu correo electrónico.',
            'email.email' => 'Ese correo no parece válido. Revisalo.',
            'equipos.max' => 'Podés seguir hasta :max equipos.',
            'equipos.*.exists' => 'Uno de los equipos elegidos ya no existe.',
            'avisos.*.in' => 'Elegiste un aviso que no existe.',
        ];
    }
}
