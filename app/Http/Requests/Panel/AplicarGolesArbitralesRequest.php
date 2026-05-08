<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel;

use App\Constants\PartidoConstants;
use App\Models\PlanillaJugador;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class AplicarGolesArbitralesRequest extends FormRequest
{
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
            'partido_id' => ['required', 'integer'],
            'idEdicion' => ['required', 'integer'],
            'tipoPartido' => ['required', 'string'],
            'idEquipo' => ['required', 'integer'],
            'horario' => ['required'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $jugadoresConGoles = PlanillaJugador::query()
                    ->where('partido_id', (int) $this->input('partido_id'))
                    ->where('partido_type', PartidoConstants::getModelClass((string) $this->input('tipoPartido')))
                    ->where('idEdicion', (int) $this->input('idEdicion'))
                    ->where('idEquipo', (int) $this->input('idEquipo'))
                    ->where('goles', '>', 0)
                    ->orderByDesc('goles')
                    ->get(['dni_jugador', 'numero_camiseta', 'goles']);

                if ($jugadoresConGoles->isEmpty()) {
                    return;
                }

                $detalle = $jugadoresConGoles
                    ->map(fn (PlanillaJugador $planilla): string => '#'.$planilla->numero_camiseta.' ('.$planilla->dni_jugador.')='.$planilla->goles)
                    ->implode(', ');

                $validator->errors()->add(
                    'idEquipo',
                    'Ya existen goles oficiales cargados en la planilla para este equipo: '.$detalle,
                );
            },
        ];
    }
}
