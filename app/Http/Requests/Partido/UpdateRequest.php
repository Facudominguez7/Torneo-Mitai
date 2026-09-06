<?php

namespace App\Http\Requests\Partido;

use App\Models\Partido;
use App\Services\PartidoService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
        return [
            'idEquipoLocal.different' => 'El equipo local debe ser distinto al equipo visitante.',
            'idEquipoVisitante.different' => 'El equipo visitante debe ser distinto al equipo local.',
            'idEquipoLocal.prohibited' => 'Los equipos no pueden modificarse porque el partido ya tiene resultado, planillas o goles confirmados.',
            'idEquipoVisitante.prohibited' => 'Los equipos no pueden modificarse porque el partido ya tiene resultado, planillas o goles confirmados.',
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

        return app(PartidoService::class)->razonesBloqueoCambioEquipos($partido) !== [];
    }
}
