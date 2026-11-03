<?php

namespace App\Http\Requests\Partido;

use App\Models\Partido;
use App\Services\PartidoService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public const ERROR_AUSENCIA = 'Los equipos no pueden modificarse porque el partido tiene una ausencia vigente.';

    public const ERROR_ACTIVIDAD = 'Los equipos no pueden modificarse porque el partido ya tiene resultado, planillas o goles confirmados.';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reenviar los equipos sin cambios no es una modificación: se descartan
     * para que el resto de los campos se pueda guardar aunque los equipos
     * estén bloqueados. Cualquier equipo distinto deja el par completo en la
     * petición y se reporta el bloqueo sobre ambos campos.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->equiposBloqueadosPorActividad()) {
            return;
        }

        $partido = $this->route('partido');
        $camposEquipos = ['idEquipoLocal', 'idEquipoVisitante'];
        $reenvioIdentico = collect($camposEquipos)->every(
            fn (string $campo): bool => ! $this->has($campo)
                || (string) $this->input($campo) === (string) $partido->{$campo},
        );

        if ($reenvioIdentico) {
            foreach ($camposEquipos as $campo) {
                $this->request->remove($campo);
            }
        }
    }

    public function rules(): array
    {
        $equiposBloqueados = $this->equiposBloqueadosPorActividad();

        return [
            'idFechas' => ['required', 'exists:fechas,id'],
            // Con actividad registrada los equipos quedan congelados; sin
            // actividad se pueden reemplazar manteniendo la validación normal.
            'idEquipoLocal' => $equiposBloqueados
                ? ['prohibited']
                : ['required', 'exists:equipos,id', 'different:idEquipoVisitante'],
            'idEquipoVisitante' => $equiposBloqueados
                ? ['prohibited']
                : ['required', 'exists:equipos,id', 'different:idEquipoLocal'],
            'idGrupo' => ['prohibited'],
            'idEdicion' => ['prohibited'],
            'idCategoria' => ['prohibited'],
            'golesEquipoLocal' => ['prohibited'],
            'golesEquipoVisitante' => ['prohibited'],
            'horario_datetime' => ['required', 'date'],
            'cancha' => ['required', 'string'],
            'jugado' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        $partido = $this->route('partido');
        $mensaje = $partido instanceof Partido && app(PartidoService::class)->tieneAusenciaVigente($partido)
            ? self::ERROR_AUSENCIA
            : self::ERROR_ACTIVIDAD;

        return [
            'idEquipoLocal.different' => 'El equipo local debe ser distinto al equipo visitante.',
            'idEquipoVisitante.different' => 'El equipo visitante debe ser distinto al equipo local.',
            'idEquipoLocal.prohibited' => $mensaje,
            'idEquipoVisitante.prohibited' => $mensaje,
            'idGrupo.prohibited' => 'El grupo no puede ser modificado desde esta pantalla.',
            'idCategoria.prohibited' => 'La categoría no puede ser modificada desde esta pantalla.',
            'idEdicion.prohibited' => 'La edición no puede ser modificada desde esta pantalla.',
            'golesEquipoLocal.prohibited' => 'Los resultados no se editan desde esta pantalla.',
            'golesEquipoVisitante.prohibited' => 'Los resultados no se editan desde esta pantalla.',
        ];
    }

    private function equiposBloqueadosPorActividad(): bool
    {
        $partido = $this->route('partido');

        if (! $partido instanceof Partido) {
            return false;
        }

        $servicio = app(PartidoService::class);

        return $servicio->razonesBloqueoCambioEquipos($partido) !== [] || $servicio->tieneAusenciaVigente($partido);
    }
}
