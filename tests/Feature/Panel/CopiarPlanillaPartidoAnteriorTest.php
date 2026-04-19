<?php

use App\Constants\PartidoConstants;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use App\Models\User;

function crearContextoCopiaPlanillaAnterior(
    string $scope,
    bool $conAntecedentes = true,
    bool $partidoAnteriorMismoDia = true,
    bool $conAsistenciasMarcadas = true,
): array
{
    $admin = User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);

    $edicion = Edicion::create([
        'nombre' => 'Edicion Copia',
    ]);

    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 18',
    ]);

    $fechaActual = Fecha::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Fecha actual',
    ]);

    $fechaAnterior = Fecha::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Fecha anterior',
    ]);

    $fechaAnteriorTiebreak = Fecha::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Fecha anterior empate',
    ]);

    $grupo = Grupo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Grupo A',
    ]);

    $equipoObjetivo = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Equipo objetivo',
        'foto' => 'objetivo.png',
    ]);

    $equipoRival = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Equipo rival',
        'foto' => 'rival.png',
    ]);

    EquipoEdicion::create([
        'idEdicion' => $edicion->id,
        'idEquipo' => $equipoObjetivo->id,
        'idCategoria' => $categoria->id,
    ]);

    EquipoEdicion::create([
        'idEdicion' => $edicion->id,
        'idEquipo' => $equipoRival->id,
        'idCategoria' => $categoria->id,
    ]);

    $partidoActual = Partido::create([
        'idCategoria' => $categoria->id,
        'idFechas' => $fechaActual->id,
        'idEquipoLocal' => $scope === 'local' ? $equipoObjetivo->id : $equipoRival->id,
        'idEquipoVisitante' => $scope === 'visitante' ? $equipoObjetivo->id : $equipoRival->id,
        'idGrupo' => $grupo->id,
        'idEdicion' => $edicion->id,
        'golesEquipoLocal' => 0,
        'golesEquipoVisitante' => 0,
        'cancha' => 1,
        'jugado' => 1,
        'horario_datetime' => '2026-04-10 09:00:00',
        'goles_confirmados_at' => now(),
    ]);

    $partidoAnterior = null;
    $partidoAnteriorEmpate = null;

    if ($conAntecedentes) {
        $horarioPartidoAnterior = $partidoAnteriorMismoDia
            ? '2026-04-10 08:00:00'
            : '2026-04-01 09:00:00';

        $horarioPartidoAnteriorEmpate = $partidoAnteriorMismoDia
            ? '2026-04-10 08:30:00'
            : '2026-04-02 09:00:00';

        $partidoAnterior = Partido::create([
            'idCategoria' => $categoria->id,
            'idFechas' => $fechaAnterior->id,
            'idEquipoLocal' => $scope === 'local' ? $equipoRival->id : $equipoObjetivo->id,
            'idEquipoVisitante' => $scope === 'local' ? $equipoObjetivo->id : $equipoRival->id,
            'idGrupo' => $grupo->id,
            'idEdicion' => $edicion->id,
            'golesEquipoLocal' => 0,
            'golesEquipoVisitante' => 0,
            'cancha' => 1,
            'jugado' => 1,
            'horario_datetime' => $horarioPartidoAnterior,
        ]);

        $partidoAnteriorEmpate = Partido::create([
            'idCategoria' => $categoria->id,
            'idFechas' => $fechaAnteriorTiebreak->id,
            'idEquipoLocal' => $scope === 'local' ? $equipoRival->id : $equipoObjetivo->id,
            'idEquipoVisitante' => $scope === 'local' ? $equipoObjetivo->id : $equipoRival->id,
            'idGrupo' => $grupo->id,
            'idEdicion' => $edicion->id,
            'golesEquipoLocal' => 0,
            'golesEquipoVisitante' => 0,
            'cancha' => 1,
            'jugado' => 1,
            'horario_datetime' => $horarioPartidoAnteriorEmpate,
        ]);
    }

    $jugadorCopiable = Jugador::create([
        'dni' => '40510001',
        'nombre' => 'Copiable',
        'apellido' => 'Uno',
        'goles_totales' => 0,
        'partidos_totales' => 0,
        'tarjetas_rojas_totales' => 0,
    ]);

    $jugadorConflicto = Jugador::create([
        'dni' => '40510002',
        'nombre' => 'Conflicto',
        'apellido' => 'Dos',
        'goles_totales' => 0,
        'partidos_totales' => 0,
        'tarjetas_rojas_totales' => 0,
    ]);

    $jugadorAusente = Jugador::create([
        'dni' => '40510003',
        'nombre' => 'Ausente',
        'apellido' => 'Tres',
        'goles_totales' => 0,
        'partidos_totales' => 0,
        'tarjetas_rojas_totales' => 0,
    ]);

    $jugadorEmpate = Jugador::create([
        'dni' => '40510004',
        'nombre' => 'Empate',
        'apellido' => 'Cuatro',
        'goles_totales' => 0,
        'partidos_totales' => 0,
        'tarjetas_rojas_totales' => 0,
    ]);

    $jugadorBloqueador = Jugador::create([
        'dni' => '40510005',
        'nombre' => 'Bloqueador',
        'apellido' => 'Cinco',
        'goles_totales' => 0,
        'partidos_totales' => 0,
        'tarjetas_rojas_totales' => 0,
    ]);

    $planillaCopiable = PlanillaJugador::create([
        'partido_id' => $partidoActual->id,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'dni_jugador' => $jugadorCopiable->dni,
        'idEquipo' => $equipoObjetivo->id,
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'numero_camiseta' => 5,
        'fecha_nacimiento' => '2010-01-01',
        'goles' => 0,
        'asistio' => false,
        'tarjeta_roja' => false,
    ]);

    $planillaConflicto = PlanillaJugador::create([
        'partido_id' => $partidoActual->id,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'dni_jugador' => $jugadorConflicto->dni,
        'idEquipo' => $equipoObjetivo->id,
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'numero_camiseta' => 8,
        'fecha_nacimiento' => '2010-01-02',
        'goles' => 0,
        'asistio' => false,
        'tarjeta_roja' => false,
    ]);

    $planillaBloqueadora = PlanillaJugador::create([
        'partido_id' => $partidoActual->id,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'dni_jugador' => $jugadorBloqueador->dni,
        'idEquipo' => $equipoObjetivo->id,
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'numero_camiseta' => 11,
        'fecha_nacimiento' => '2010-01-05',
        'goles' => 0,
        'asistio' => false,
        'tarjeta_roja' => false,
    ]);

    if ($conAntecedentes) {
        PlanillaJugador::create([
            'partido_id' => $partidoAnterior->id,
            'partido_type' => PartidoConstants::CLASS_PARTIDO,
            'dni_jugador' => $jugadorCopiable->dni,
            'idEquipo' => $equipoObjetivo->id,
            'idEdicion' => $edicion->id,
            'idCategoria' => $categoria->id,
            'numero_camiseta' => 7,
            'fecha_nacimiento' => '2010-01-01',
            'goles' => 0,
            'asistio' => $conAsistenciasMarcadas,
            'tarjeta_roja' => false,
        ]);

        PlanillaJugador::create([
            'partido_id' => $partidoAnterior->id,
            'partido_type' => PartidoConstants::CLASS_PARTIDO,
            'dni_jugador' => $jugadorConflicto->dni,
            'idEquipo' => $equipoObjetivo->id,
            'idEdicion' => $edicion->id,
            'idCategoria' => $categoria->id,
            'numero_camiseta' => 11,
            'fecha_nacimiento' => '2010-01-02',
            'goles' => 0,
            'asistio' => $conAsistenciasMarcadas,
            'tarjeta_roja' => false,
        ]);

        PlanillaJugador::create([
            'partido_id' => $partidoAnterior->id,
            'partido_type' => PartidoConstants::CLASS_PARTIDO,
            'dni_jugador' => $jugadorAusente->dni,
            'idEquipo' => $equipoObjetivo->id,
            'idEdicion' => $edicion->id,
            'idCategoria' => $categoria->id,
            'numero_camiseta' => 9,
            'fecha_nacimiento' => '2010-01-03',
            'goles' => 0,
            'asistio' => $conAsistenciasMarcadas,
            'tarjeta_roja' => false,
        ]);

        PlanillaJugador::create([
            'partido_id' => $partidoAnteriorEmpate->id,
            'partido_type' => PartidoConstants::CLASS_PARTIDO,
            'dni_jugador' => $jugadorEmpate->dni,
            'idEquipo' => $equipoObjetivo->id,
            'idEdicion' => $edicion->id,
            'idCategoria' => $categoria->id,
            'numero_camiseta' => 99,
            'fecha_nacimiento' => '2010-01-04',
            'goles' => 0,
            'asistio' => $conAsistenciasMarcadas,
            'tarjeta_roja' => false,
        ]);
    }

    PlanillaJugador::query()
        ->whereIn('id', [$planillaCopiable->id, $planillaConflicto->id, $planillaBloqueadora->id])
        ->update([
            'updated_at' => now()->subMinute(),
        ]);

    return compact(
        'admin',
        'edicion',
        'categoria',
        'equipoObjetivo',
        'equipoRival',
        'partidoActual',
        'partidoAnterior',
        'partidoAnteriorEmpate',
        'planillaCopiable',
        'planillaConflicto',
        'planillaBloqueadora',
        'jugadorCopiable',
        'jugadorConflicto',
        'jugadorAusente',
        'jugadorBloqueador',
        'jugadorEmpate',
    );
}

function postCopiarPlanillaJsonConCsrf($testCase, string $ruta, array $parametros)
{
    $token = 'token-copia-planilla-test';

    return $testCase
        ->withSession(['_token' => $token])
        ->withHeader('Accept', 'application/json')
        ->post($ruta, array_merge($parametros, ['_token' => $token]));
}

it('copia asistencia y camiseta desde el partido anterior y refresca la version para autosave', function (string $scope) {
    $contexto = crearContextoCopiaPlanillaAnterior($scope);

    $response = postCopiarPlanillaJsonConCsrf(
        $this->actingAs($contexto['admin']),
        route('planilla.copiarPartidoAnterior'),
        [
            'partido_id' => $contexto['partidoActual']->id,
            'idEdicion' => $contexto['edicion']->id,
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'horario' => '09:00',
            'scope' => $scope,
        ]
    );

    $response
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('data.scope', $scope)
        ->assertJsonPath('data.partido_anterior_id', $contexto['partidoAnterior']->id)
        ->assertJsonPath('data.resumen.copiados', 1)
        ->assertJsonPath('data.resumen.omitidos', 1)
        ->assertJsonPath('data.resumen.conflictos', 1)
        ->assertJsonPath('data.filas_actualizadas.0.dni', $contexto['jugadorCopiable']->dni)
        ->assertJsonPath('data.confirmacion_invalidada', true);

    $planillaCopiable = $contexto['planillaCopiable']->fresh();
    $planillaConflicto = $contexto['planillaConflicto']->fresh();
    $planillaBloqueadora = $contexto['planillaBloqueadora']->fresh();

    expect($planillaCopiable->numero_camiseta)->toBe(7);
    expect((bool) $planillaCopiable->asistio)->toBeTrue();
    expect($planillaCopiable->updated_at->greaterThan(now()->subMinute()))->toBeTrue();

    expect($planillaConflicto->numero_camiseta)->toBe(8);
    expect((bool) $planillaConflicto->asistio)->toBeFalse();

    expect($planillaBloqueadora->numero_camiseta)->toBe(11);
    expect((bool) $planillaBloqueadora->asistio)->toBeFalse();

    $autosaveResponse = postCopiarPlanillaJsonConCsrf(
        $this->actingAs($contexto['admin']),
        route('planilla.actualizarJugadores'),
        [
            'partido_id' => $contexto['partidoActual']->id,
            'idEdicion' => $contexto['edicion']->id,
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'horario' => '09:00',
            'jugadores' => [
                $contexto['jugadorCopiable']->dni => [
                    'numero_camiseta' => 12,
                    'goles' => 1,
                    'asistencia' => '1',
                    'tarjeta_roja' => '0',
                    'updated_at' => $planillaCopiable->updated_at->toIso8601String(),
                ],
            ],
        ]
    );

    $autosaveResponse
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('data.dni', $contexto['jugadorCopiable']->dni);

    postCopiarPlanillaJsonConCsrf(
        $this->actingAs($contexto['admin']),
        route('planilla.actualizarJugadores'),
        [
            'partido_id' => $contexto['partidoActual']->id,
            'idEdicion' => $contexto['edicion']->id,
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'horario' => '09:00',
            'jugadores' => [
                $contexto['jugadorCopiable']->dni => [
                    'numero_camiseta' => 13,
                    'goles' => 2,
                    'asistencia' => '1',
                    'tarjeta_roja' => '0',
                    'updated_at' => $planillaCopiable->updated_at->copy()->subMinute()->toIso8601String(),
                ],
            ],
        ]
    )
        ->assertStatus(409)
        ->assertJsonPath('status', 'conflict');
})->with(['local', 'visitante']);

it('rechaza la copia cuando no existe un antecedente válido', function () {
    $contexto = crearContextoCopiaPlanillaAnterior('local', false);

    postCopiarPlanillaJsonConCsrf(
        $this->actingAs($contexto['admin']),
        route('planilla.copiarPartidoAnterior'),
        [
            'partido_id' => $contexto['partidoActual']->id,
            'idEdicion' => $contexto['edicion']->id,
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'horario' => '09:00',
            'scope' => 'local',
        ]
    )
        ->assertNotFound()
        ->assertJsonPath('status', 'no_previous_match');
});

it('rechaza la copia cuando el antecedente es ambiguo', function () {
    $contexto = crearContextoCopiaPlanillaAnterior('local');

    $partidoAmbiguo = Partido::create([
        'idCategoria' => $contexto['categoria']->id,
        'idFechas' => $contexto['partidoAnterior']->idFechas,
        'idEquipoLocal' => $contexto['equipoRival']->id,
        'idEquipoVisitante' => $contexto['equipoObjetivo']->id,
        'idGrupo' => $contexto['partidoAnterior']->idGrupo,
        'idEdicion' => $contexto['edicion']->id,
        'golesEquipoLocal' => 0,
        'golesEquipoVisitante' => 0,
        'cancha' => 1,
        'jugado' => 1,
        'horario_datetime' => '2026-04-10 08:00:00',
    ]);

    PlanillaJugador::create([
        'partido_id' => $partidoAmbiguo->id,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'dni_jugador' => $contexto['jugadorEmpate']->dni,
        'idEquipo' => $contexto['equipoObjetivo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'numero_camiseta' => 99,
        'fecha_nacimiento' => '2010-01-04',
        'goles' => 0,
        'asistio' => true,
        'tarjeta_roja' => false,
    ]);

    postCopiarPlanillaJsonConCsrf(
        $this->actingAs($contexto['admin']),
        route('planilla.copiarPartidoAnterior'),
        [
            'partido_id' => $contexto['partidoActual']->id,
            'idEdicion' => $contexto['edicion']->id,
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'horario' => '09:00',
            'scope' => 'local',
        ]
    )
        ->assertStatus(409)
        ->assertJsonPath('status', 'ambiguous_previous_match');
});

it('muestra botones para copiar desde el partido anterior por equipo', function () {
    $contexto = crearContextoCopiaPlanillaAnterior('local');

    $this->actingAs($contexto['admin'])
        ->get(route('planilla.show', [
            'partidoId' => $contexto['partidoActual']->id,
            'idEdicion' => $contexto['edicion']->id,
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'horario' => '09:00',
        ]))
        ->assertOk()
        ->assertSee('Copiar local desde partido anterior', false)
        ->assertDontSee('Copiar visitante desde partido anterior', false);
});

it('oculta botones para copiar cuando no existe partido anterior del mismo dia', function () {
    $contexto = crearContextoCopiaPlanillaAnterior('local', true, false, true);

    $this->actingAs($contexto['admin'])
        ->get(route('planilla.show', [
            'partidoId' => $contexto['partidoActual']->id,
            'idEdicion' => $contexto['edicion']->id,
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'horario' => '09:00',
        ]))
        ->assertOk()
        ->assertDontSee('Copiar local desde partido anterior', false)
        ->assertDontSee('Copiar visitante desde partido anterior', false);
});

it('oculta botones para copiar cuando no hay asistencias marcadas en el partido anterior del mismo dia', function () {
    $contexto = crearContextoCopiaPlanillaAnterior('local', true, true, false);

    $this->actingAs($contexto['admin'])
        ->get(route('planilla.show', [
            'partidoId' => $contexto['partidoActual']->id,
            'idEdicion' => $contexto['edicion']->id,
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'horario' => '09:00',
        ]))
        ->assertOk()
        ->assertDontSee('Copiar local desde partido anterior', false)
        ->assertDontSee('Copiar visitante desde partido anterior', false);
});
