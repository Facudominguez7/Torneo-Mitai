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

function crearContextoPlanillaConfirmacion(): array
{
    $admin = User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);

    $edicion = Edicion::create([
        'nombre' => 'Edicion Confirmacion',
    ]);

    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 16',
    ]);

    $fecha = Fecha::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Fecha 1',
    ]);

    $grupo = Grupo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Grupo A',
    ]);

    $equipoLocal = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Local Confirmacion',
        'foto' => 'local.png',
    ]);

    $equipoVisitante = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Visitante Confirmacion',
        'foto' => 'visitante.png',
    ]);

    EquipoEdicion::create([
        'idEdicion' => $edicion->id,
        'idEquipo' => $equipoLocal->id,
        'idCategoria' => $categoria->id,
    ]);

    EquipoEdicion::create([
        'idEdicion' => $edicion->id,
        'idEquipo' => $equipoVisitante->id,
        'idCategoria' => $categoria->id,
    ]);

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

    $jugador = Jugador::create([
        'dni' => '40500001',
        'nombre' => 'Juan',
        'apellido' => 'Confirmado',
        'goles_totales' => 0,
        'partidos_totales' => 0,
        'tarjetas_rojas_totales' => 0,
    ]);

    $planilla = PlanillaJugador::create([
        'partido_id' => $partido->id,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'dni_jugador' => $jugador->dni,
        'idEquipo' => $equipoLocal->id,
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'numero_camiseta' => 10,
        'fecha_nacimiento' => '2010-02-01',
        'goles' => 0,
        'asistio' => false,
        'tarjeta_roja' => false,
    ]);

    return compact('admin', 'edicion', 'categoria', 'equipoLocal', 'equipoVisitante', 'partido', 'jugador', 'planilla');
}

function parametrosBasePlanilla(array $contexto): array
{
    return [
        'partido_id' => $contexto['partido']->id,
        'idEdicion' => $contexto['edicion']->id,
        'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
        'horario' => '09:00',
    ];
}

function postPlanillaConCsrf($testCase, string $ruta, array $parametros)
{
    $token = 'token-planilla-test';

    return $testCase
        ->withSession(['_token' => $token])
        ->post($ruta, array_merge($parametros, ['_token' => $token]));
}

it('confirma goles desde la accion admin explicita', function () {
    $contexto = crearContextoPlanillaConfirmacion();

    postPlanillaConCsrf(
        $this->actingAs($contexto['admin']),
        route('planilla.confirmarGoles'),
        parametrosBasePlanilla($contexto)
    )
        ->assertRedirect();

    expect($contexto['partido']->fresh()->goles_confirmados_at)->not->toBeNull();
});

it('quita la confirmacion de goles desde la accion admin explicita', function () {
    $contexto = crearContextoPlanillaConfirmacion();
    $contexto['partido']->update([
        'goles_confirmados_at' => now(),
    ]);

    postPlanillaConCsrf(
        $this->actingAs($contexto['admin']),
        route('planilla.quitarConfirmacionGoles'),
        parametrosBasePlanilla($contexto)
    )
        ->assertRedirect();

    expect($contexto['partido']->fresh()->goles_confirmados_at)->toBeNull();
});

it('invalida la confirmacion cuando se edita un campo persistido de la planilla', function () {
    $contexto = crearContextoPlanillaConfirmacion();
    $contexto['partido']->update([
        'goles_confirmados_at' => now(),
    ]);

    postPlanillaConCsrf(
        $this->actingAs($contexto['admin']),
        route('planilla.actualizarJugadores'),
        array_merge(parametrosBasePlanilla($contexto), [
            'jugadores' => [
                $contexto['jugador']->dni => [
                    'goles' => 2,
                    'asistencia' => 'on',
                    'numero_camiseta' => 10,
                ],
            ],
        ])
    )
        ->assertRedirect();

    $partidoActualizado = $contexto['partido']->fresh();
    $planillaActualizada = $contexto['planilla']->fresh();

    expect($partidoActualizado->goles_confirmados_at)->toBeNull();
    expect($planillaActualizada->goles)->toBe(2);
    expect((bool) $planillaActualizada->asistio)->toBeTrue();
});

it('no muestra la advertencia de goles al actualizar jugadores', function () {
    $contexto = crearContextoPlanillaConfirmacion();
    $contexto['partido']->update([
        'golesEquipoLocal' => 1,
        'golesEquipoVisitante' => 0,
    ]);

    postPlanillaConCsrf(
        $this->actingAs($contexto['admin']),
        route('planilla.actualizarJugadores'),
        array_merge(parametrosBasePlanilla($contexto), [
            'jugadores' => [
                $contexto['jugador']->dni => [
                    'goles' => 0,
                    'asistencia' => 'on',
                    'numero_camiseta' => 10,
                ],
            ],
        ])
    )
        ->assertRedirect()
        ->assertSessionMissing('warning');
});

it('confirma goles con warning cuando hay diferencia con el resultado oficial', function () {
    $contexto = crearContextoPlanillaConfirmacion();
    $contexto['partido']->update([
        'golesEquipoLocal' => 1,
        'golesEquipoVisitante' => 0,
    ]);

    postPlanillaConCsrf(
        $this->actingAs($contexto['admin']),
        route('planilla.confirmarGoles'),
        parametrosBasePlanilla($contexto)
    )
        ->assertRedirect()
        ->assertSessionHas('status', 'Goles confirmados correctamente.')
        ->assertSessionHas('warning', fn (string $warning): bool => str_contains($warning, 'Atención: la suma de goles individuales no coincide'));

    expect($contexto['partido']->fresh()->goles_confirmados_at)->not->toBeNull();
});

it('muestra estado pendiente de carga cuando el resultado existe pero falta confirmar goles', function () {
    $contexto = crearContextoPlanillaConfirmacion();
    $contexto['partido']->update([
        'golesEquipoLocal' => 2,
        'golesEquipoVisitante' => 1,
        'goles_confirmados_at' => null,
    ]);

    $this->actingAs($contexto['admin'])
        ->get(route('planilla.show', [
            'partidoId' => $contexto['partido']->id,
            'idEdicion' => $contexto['edicion']->id,
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'horario' => '09:00',
        ]))
        ->assertOk()
        ->assertSee('Goles pendientes de carga', false);
});

it('muestra estado confirmado cuando los goles ya fueron confirmados', function () {
    $contexto = crearContextoPlanillaConfirmacion();
    $contexto['partido']->update([
        'goles_confirmados_at' => now(),
    ]);

    $this->actingAs($contexto['admin'])
        ->get(route('planilla.show', [
            'partidoId' => $contexto['partido']->id,
            'idEdicion' => $contexto['edicion']->id,
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'horario' => '09:00',
        ]))
        ->assertOk()
        ->assertSee('Goles confirmados', false);
});

it('muestra estado neutral cuando todavia no hay resultado oficial cargado', function () {
    $contexto = crearContextoPlanillaConfirmacion();
    $contexto['partido']->update([
        'golesEquipoLocal' => null,
        'golesEquipoVisitante' => null,
        'goles_confirmados_at' => null,
    ]);

    $this->actingAs($contexto['admin'])
        ->get(route('planilla.show', [
            'partidoId' => $contexto['partido']->id,
            'idEdicion' => $contexto['edicion']->id,
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'horario' => '09:00',
        ]))
        ->assertOk()
        ->assertSee('Resultado pendiente de carga', false)
        ->assertDontSee('Goles pendientes de carga', false);
});

it('muestra la accion para desmarcar asistencia a todos en la planilla', function () {
    $contexto = crearContextoPlanillaConfirmacion();

    $this->actingAs($contexto['admin'])
        ->get(route('planilla.show', [
            'partidoId' => $contexto['partido']->id,
            'idEdicion' => $contexto['edicion']->id,
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'horario' => '09:00',
        ]))
        ->assertOk()
        ->assertSee('Desmarcar asistencia a todos', false);
});
