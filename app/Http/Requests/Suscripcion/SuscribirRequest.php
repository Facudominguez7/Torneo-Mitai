<?php

namespace App\Http\Requests\Suscripcion;

use App\Services\EmailDomainGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SuscribirRequest extends FormRequest
{
    /** Bolsa propia: el formulario vive en la home junto a otros formularios. */
    protected $errorBag = 'suscripcion';

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
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
                    if (is_string($value) && EmailDomainGuard::isBlocked($value)) {
                        $fail(EmailDomainGuard::validationMessage());
                    }
                },
            ],
            // El formulario manda "avisos" vacío cuando no se marca ninguno; sin la clave se reciben todos.
            'avisos' => ['sometimes', 'nullable', 'array'],
            'avisos.*' => ['string', Rule::in(array_keys(config('suscripciones.avisos')))],
        ];
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->has('avisos') && empty($this->input('avisos'))) {
                    $validator->errors()->add('avisos', 'Elegí al menos un aviso.');
                }
            },
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
            'avisos.*.in' => 'Elegiste un aviso que no existe.',
        ];
    }
}
