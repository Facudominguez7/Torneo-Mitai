<?php

use App\Constants\PartidoConstants;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fecha;
use App\Models\GolArbitroReportado;
use App\Models\Grupo;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use App\Models\TablaGoleador;
use App\Models\User;

function crearContextoAplicarGolesArbitrales(): array
{
    $admin = User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);

    $edicion = Edicion::create(['nombre' => 'Edicion Aplicar Arbitral']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 16']);
    $fecha = Fecha::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Fecha 1']);
    $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Grupo A']);

    $equipoLocal = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Local Arbitral',
        'foto' => 'local.png',
    ]);

    $equipoVisitante = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Visitante Arbitral',
        'foto' => 'visitante.png',
    ]);

    EquipoEdicion::create(['idEdicion' => $edicion->id, 'idEquipo' => $equipoLocal->id, 'idCategoria' => $categoria->id]);
    EquipoEdicion::create(['idEdicion' => $edicion->id, 'idEquipo' => $equipoVisitante->id, 'idCategoria' => $categoria->id]);

    $partido = Partido::create([
        'idCategoria' => $categoria->id,
        'idFechas' => $fecha->id,
        'idEquipoLocal' => $equipoLocal->id,
        'idEquipoVisitante' => $equipoVisitante->id,
        'idGrupo' => $grupo->id,
        'idEdicion' => $edicion->id,
        'golesEquipoLocal' => 0,
        'golesEquipoVisitante' => 0,
        'cancha' => 1,
        'jugado' => 1,
        'horario_datetime' => '2026-04-01 09:00:00',
    ]);

    $jugadorLocal = Jugador::create([
        'dni' => '40510001',
        'nombre' => 'Juan',
        'apellido' => 'Local',
        'goles_totales' => 0,
        'partidos_totales' => 0,
        'tarjetas_rojas_totales' => 0,
    ]);

    $jugadorVisitante = Jugador::create([
        'dni' => '40510002',
        'nombre' => 'Pedro',
        'apellido' => 'Visitante',
        'goles_totales' => 0,
        'partidos_totales' => 0,
        'tarjetas_rojas_totales' => 0,
    ]);

    $planillaLocal = PlanillaJugador::create([
        'partido_id' => $partido->id,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'dni_jugador' => $jugadorLocal->dni,
        'idEquipo' => $equipoLocal->id,
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'numero_camiseta' => 10,
        'fecha_nacimiento' => '2010-02-01',
        'goles' => 0,
        'asistio' => true,
        'tarjeta_roja' => false,
    ]);

    $planillaVisitante = PlanillaJugador::create([
        'partido_id' => $partido->id,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'dni_jugador' => $jugadorVisitante->dni,
        'idEquipo' => $equipoVisitante->id,
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'numero_camiseta' => 9,
        'fecha_nacimiento' => '2010-03-01',
        'goles' => 0,
        'asistio' => true,
        'tarjeta_roja' => false,
    ]);

    return compact('admin', 'edicion', 'categoria', 'equipoLocal', 'equipoVisitante', 'partido', 'jugadorLocal', 'planillaLocal', 'planillaVisitante');
}

function parametrosBaseAplicarGoles(array $contexto): array
{
    return [
        'partido_id' => $contexto['partido']->id,
        'idEdicion' => $contexto['edicion']->id,
        'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
        'horario' => '09:00',
    ];
}

function postAplicarGolesConCsrf($testCase, string $ruta, array $parametros)
{
    $token = 'token-aplicar-goles';

    return $testCase
        ->withSession(['_token' => $token])
        ->post($ruta, array_merge($parametros, ['_token' => $token]));
}

it('renderiza estados aplicable e ignorado en planilla arbitral', function () {
    $contexto = crearContextoAplicarGolesArbitrales();

    GolArbitroReportado::create([
        'partido_id' => $contexto['partido']->id,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $contexto['equipoLocal']->id,
        'idPlanillaJugador' => $contexto['planillaLocal']->id,
        'numero_camiseta' => 10,
        'goles_reportados' => 2,
        'es_inconsistencia' => false,
        'motivo_inconsistencia' => null,
    ]);

    GolArbitroReportado::create([
        'partido_id' => $contexto['partido']->id,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $contexto['equipoVisitante']->id,
        'idPlanillaJugador' => null,
        'numero_camiseta' => 44,
        'goles_reportados' => 1,
        'es_inconsistencia' => true,
        'motivo_inconsistencia' => 'Camiseta no encontrada o sin asistencia en la planilla oficial.',
    ]);

    $this->actingAs($contexto['admin'])
        ->get(route('planilla.show', [
            'partidoId' => $contexto['partido']->id,
            'idEdicion' => $contexto['edicion']->id,
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'horario' => '09:00',
        ]))
        ->assertOk()
        ->assertSee('Camiseta 10 · 2 gol(es)', false)
        ->assertSee('Aplicar goles válidos', false);
});

it('aplica goles arbitrales y actualiza estadisticas del goleador', function () {
    $contexto = crearContextoAplicarGolesArbitrales();

    GolArbitroReportado::create([
        'partido_id' => $contexto['partido']->id,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $contexto['equipoLocal']->id,
        'idPlanillaJugador' => $contexto['planillaLocal']->id,
        'numero_camiseta' => 10,
        'goles_reportados' => 3,
        'es_inconsistencia' => false,
        'motivo_inconsistencia' => null,
    ]);

    postAplicarGolesConCsrf(
        $this->actingAs($contexto['admin']),
        route('planilla.aplicarGolesArbitrales'),
        array_merge(parametrosBaseAplicarGoles($contexto), [
            'idEquipo' => $contexto['equipoLocal']->id,
        ])
    )
        ->assertRedirect()
        ->assertSessionHas('status', 'Goles arbitrales aplicados sobre la planilla oficial del equipo.');

    expect((int) $contexto['planillaLocal']->fresh()->goles)->toBe(3);
    expect((int) $contexto['jugadorLocal']->fresh()->goles_totales)->toBe(3);
    expect(TablaGoleador::query()->where('dni_jugador', $contexto['jugadorLocal']->dni)->value('cantidadGoles'))->toBe(3);
});

it('bloquea la aplicacion completa del equipo si existe un gol oficial', function () {
    $contexto = crearContextoAplicarGolesArbitrales();
    $contexto['planillaLocal']->update(['goles' => 1]);

    GolArbitroReportado::create([
        'partido_id' => $contexto['partido']->id,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $contexto['equipoLocal']->id,
        'idPlanillaJugador' => $contexto['planillaLocal']->id,
        'numero_camiseta' => 10,
        'goles_reportados' => 2,
        'es_inconsistencia' => false,
        'motivo_inconsistencia' => null,
    ]);

    postAplicarGolesConCsrf(
        $this->actingAs($contexto['admin']),
        route('planilla.aplicarGolesArbitrales'),
        array_merge(parametrosBaseAplicarGoles($contexto), [
            'idEquipo' => $contexto['equipoLocal']->id,
        ])
    )
        ->assertRedirect()
        ->assertSessionHasErrors('idEquipo');

    expect((int) $contexto['planillaLocal']->fresh()->goles)->toBe(1);
});
