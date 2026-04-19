<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

final class ActualizarPlanillaJugadoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'partido_id' => ['required', 'integer'],
            'idEdicion' => ['required', 'integer'],
            'tipoPartido' => ['required', 'string'],
            'horario' => ['required'],
            'jugadores' => ['required', 'array', 'min:1'],
            'jugadores.*.numero_camiseta' => ['nullable', 'integer', 'min:0', 'max:999'],
            'jugadores.*.goles' => ['nullable', 'integer', 'min:0', 'max:99'],
            'jugadores.*.asistencia' => ['nullable', 'in:0,1,on,off,true,false'],
            'jugadores.*.tarjeta_roja' => ['nullable', 'in:0,1,on,off,true,false'],
            'jugadores.*.updated_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'partido_id.required' => 'El partido es obligatorio.',
            'idEdicion.required' => 'La edición es obligatoria.',
            'tipoPartido.required' => 'El tipo de partido es obligatorio.',
            'horario.required' => 'El horario es obligatorio.',
            'jugadores.required' => 'Debes enviar al menos un jugador.',
            'jugadores.array' => 'La planilla enviada no tiene el formato esperado.',
            'jugadores.min' => 'Debes enviar al menos un jugador.',
            'jugadores.*.numero_camiseta.integer' => 'El número de camiseta debe ser un número entero.',
            'jugadores.*.numero_camiseta.min' => 'El número de camiseta no puede ser negativo.',
            'jugadores.*.numero_camiseta.max' => 'El número de camiseta no puede superar 999.',
            'jugadores.*.goles.integer' => 'Los goles deben ser un número entero.',
            'jugadores.*.goles.min' => 'Los goles no pueden ser negativos.',
            'jugadores.*.goles.max' => 'Los goles no pueden superar 99.',
            'jugadores.*.updated_at.date' => 'La versión de la fila no tiene un formato válido.',
        ];
    }
}
