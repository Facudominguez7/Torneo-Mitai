<?php
namespace App\Http\Requests\Jugador;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Jugador;

class StoreRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     *
     * @return bool
     */
    public function authorize()
    {
        // Si tienes alguna lógica de autorización, puedes agregarla aquí
        // Por ejemplo, puedes verificar si el usuario está autenticado o tiene permisos
        return true;  // Asegúrate de cambiar esto si necesitas restricciones
    }

    /**
     * Obtiene las reglas de validación para la solicitud.
     *
     * @return array
     */
    public function rules()
    {
        // Regla de DNI: en creación es unique simple; en actualización ignora el propio registro
        $dniUnique = Rule::unique('jugadores', 'dni');

        // Detectar si es actualización y obtener el jugador de la ruta (model binding)
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $jugador = $this->route('jugador');

            if ($jugador instanceof Jugador) {
                // Preferimos ignorar por columna id si existe, en su defecto por dni
                if (!is_null($jugador->id)) {
                    $dniUnique = $dniUnique->ignore($jugador->id, 'id');
                } elseif (!is_null($jugador->dni)) {
                    $dniUnique = $dniUnique->ignore($jugador->dni, 'dni');
                }
            } elseif (is_numeric($jugador)) {
                // Si llega un ID numérico, ignorar por id
                $dniUnique = $dniUnique->ignore((int) $jugador, 'id');
            }
        }

        return [
            'nombre' => 'required|string|max:255',
            'dni' => ['required', 'digits:8', $dniUnique],
            'numeroCamiseta' => 'required|integer|min:1|max:99',
            'idEquipo' => 'required|exists:equipos,id',
            'idCategoria' => 'required|exists:categorias,id',
            'idEdicion' => 'required|exists:ediciones,id',
        ];
    }

    /**
     * Mensajes personalizados de validación.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'nombre.required' => 'El nombre del jugador es obligatorio.',
            'dni.required' => 'El DNI del jugador es obligatorio.',
            'dni.digits' => 'El DNI debe tener 8 dígitos.',
            'dni.unique' => 'El DNI ya está registrado en la base de datos.',
            'numeroCamiseta.required' => 'El número de camiseta es obligatorio.',
            'numeroCamiseta.integer' => 'El número de camiseta debe ser un número entero.',
            'numeroCamiseta.min' => 'El número de camiseta debe ser al menos 1.',
            'numeroCamiseta.max' => 'El número de camiseta no puede ser mayor a 99.',
            'idEquipo.required' => 'El equipo es obligatorio.',
            'idEquipo.exists' => 'El equipo seleccionado no existe.',
            'idCategoria.required' => 'La categoría es obligatoria.',
            'idCategoria.exists' => 'La categoría seleccionada no existe.',
            'idEdicion.required' => 'La edición es obligatoria.',
            'idEdicion.exists' => 'La edición seleccionada no existe.',
        ];
    }
}
