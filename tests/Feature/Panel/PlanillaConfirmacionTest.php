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
use App\Models\ListaBuenaFe;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use App\Models\TablaGoleador;
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

    // El jugador debe estar activo en la Lista de Buena Fe para que la vista
    // de planilla no lo procese como baja al sincronizar.
    ListaBuenaFe::create([
        'dni_jugador' => $jugador->dni,
        'idEquipo' => $equipoLocal->id,
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'fecha_nacimiento' => '2010-02-01',
        'activo' => true,
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

function postPlanillaJsonConCsrf($testCase, string $ruta, array $parametros)
{
    $token = 'token-planilla-test';

    return $testCase
        ->withSession(['_token' => $token])
        ->withHeader('Accept', 'application/json')
        ->post($ruta, array_merge($parametros, ['_token' => $token]));
}

it('confirma goles sin warning cuando la suma individual coincide con el resultado oficial', function () {
    $contexto = crearContextoPlanillaConfirmacion();
    $jugadorVisitante = Jugador::create([
        'dni' => '40500002',
        'nombre' => 'Pedro',
        'apellido' => 'Visitante',
        'goles_totales' => 0,
        'partidos_totales' => 0,
        'tarjetas_rojas_totales' => 0,
    ]);

    PlanillaJugador::create([
        'partido_id' => $contexto['partido']->id,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'dni_jugador' => $jugadorVisitante->dni,
        'idEquipo' => $contexto['equipoVisitante']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'numero_camiseta' => 9,
        'fecha_nacimiento' => '2010-03-01',
        'goles' => 1,
        'asistio' => false,
        'tarjeta_roja' => false,
    ]);

    $contexto['planilla']->update([
        'goles' => 2,
        'asistio' => true,
    ]);

    $contexto['partido']->update([
        'golesEquipoLocal' => 2,
        'golesEquipoVisitante' => 1,
    ]);

    postPlanillaConCsrf(
        $this->actingAs($contexto['admin']),
        route('planilla.confirmarGoles'),
        parametrosBasePlanilla($contexto)
    )
        ->assertRedirect()
        ->assertSessionHas('status', 'Goles confirmados correctamente.')
        ->assertSessionMissing('warning');

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

it('persiste una fila por autosave json con payload parcial', function () {
    $contexto = crearContextoPlanillaConfirmacion();
    $contexto['partido']->update([
        'goles_confirmados_at' => now(),
    ]);
    $otroJugador = Jugador::create([
        'dni' => '40500003',
        'nombre' => 'Luis',
        'apellido' => 'Reserva',
        'goles_totales' => 0,
        'partidos_totales' => 0,
        'tarjetas_rojas_totales' => 0,
    ]);

    $otraPlanilla = PlanillaJugador::create([
        'partido_id' => $contexto['partido']->id,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'dni_jugador' => $otroJugador->dni,
        'idEquipo' => $contexto['equipoLocal']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'numero_camiseta' => 7,
        'fecha_nacimiento' => '2010-04-01',
        'goles' => 1,
        'asistio' => false,
        'tarjeta_roja' => false,
    ]);

    PlanillaJugador::query()
        ->whereKey($contexto['planilla']->id)
        ->update([
            'updated_at' => now()->subMinute(),
        ]);

    $planillaOriginal = $contexto['planilla']->fresh();

    postPlanillaJsonConCsrf(
        $this->actingAs($contexto['admin']),
        route('planilla.actualizarJugadores'),
        array_merge(parametrosBasePlanilla($contexto), [
            'jugadores' => [
                $contexto['jugador']->dni => [
                    'numero_camiseta' => 11,
                    'goles' => 2,
                    'asistencia' => '1',
                    'tarjeta_roja' => '0',
                    'updated_at' => $planillaOriginal->updated_at->toIso8601String(),
                ],
            ],
        ])
    )
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('data.dni', $contexto['jugador']->dni)
        ->assertJsonPath('data.confirmacion_invalidada', true);

    $planillaActualizada = $contexto['planilla']->fresh();
    $otraPlanillaActualizada = $otraPlanilla->fresh();

    expect($planillaActualizada->numero_camiseta)->toBe(11);
    expect($planillaActualizada->goles)->toBe(2);
    expect((bool) $planillaActualizada->asistio)->toBeTrue();
    expect((bool) $planillaActualizada->tarjeta_roja)->toBeFalse();
    expect($planillaActualizada->updated_at->greaterThan($planillaOriginal->updated_at))->toBeTrue();

    expect($otraPlanillaActualizada->numero_camiseta)->toBe(7);
    expect($otraPlanillaActualizada->goles)->toBe(1);
    expect((bool) $otraPlanillaActualizada->asistio)->toBeFalse();
});

it('rechaza un autosave con updated_at desfasado como conflicto', function () {
    $contexto = crearContextoPlanillaConfirmacion();
    $planillaOriginal = $contexto['planilla']->fresh();

    postPlanillaJsonConCsrf(
        $this->actingAs($contexto['admin']),
        route('planilla.actualizarJugadores'),
        array_merge(parametrosBasePlanilla($contexto), [
            'jugadores' => [
                $contexto['jugador']->dni => [
                    'numero_camiseta' => 13,
                    'goles' => 3,
                    'asistencia' => '1',
                    'tarjeta_roja' => '0',
                    'updated_at' => $planillaOriginal->updated_at->copy()->subHour()->toIso8601String(),
                ],
            ],
        ])
    )
        ->assertStatus(409)
        ->assertJsonPath('status', 'conflict')
        ->assertJsonPath('data.current.numero_camiseta', $planillaOriginal->numero_camiseta)
        ->assertJsonPath('data.current.goles', $planillaOriginal->goles);

    $planillaSinCambios = $contexto['planilla']->fresh();

    expect($planillaSinCambios->numero_camiseta)->toBe($planillaOriginal->numero_camiseta);
    expect($planillaSinCambios->goles)->toBe($planillaOriginal->goles);
    expect($planillaSinCambios->updated_at->toIso8601String())->toBe($planillaOriginal->updated_at->toIso8601String());
});

it('mantiene el redirect html para el submit tradicional', function () {
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
                    'numero_camiseta' => 12,
                    'goles' => 1,
                    'asistencia' => 'on',
                    'tarjeta_roja' => '0',
                ],
            ],
        ])
    )
        ->assertRedirect()
        ->assertSessionHas('status', 'Jugadores actualizados correctamente. La confirmacion de goles se removio porque la planilla fue modificada.');
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

it('no muestra el boton actualizar todos en la planilla renderizada', function () {
    $contexto = crearContextoPlanillaConfirmacion();

    $this->actingAs($contexto['admin'])
        ->get(route('planilla.show', [
            'partidoId' => $contexto['partido']->id,
            'idEdicion' => $contexto['edicion']->id,
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'horario' => '09:00',
        ]))
        ->assertOk()
        ->assertDontSee('Actualizar Todos', false);
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

it('explica la aplicacion de goles arbitrales al guardar y sus restricciones', function () {
    $contexto = crearContextoPlanillaConfirmacion();

    $this->actingAs($contexto['admin'])
        ->get(route('planilla.show', [
            'partidoId' => $contexto['partido']->id,
            'idEdicion' => $contexto['edicion']->id,
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'horario' => '09:00',
        ]))
        ->assertOk()
        ->assertSeeText('Aplicación al guardar')
        ->assertSeeText('Solo goles válidos')
        ->assertSeeText('Los goles arbitrales vinculados a jugadores presentes se aplican al guardar y actualizan sus estadísticas')
        ->assertSeeText('siempre que el equipo no tenga goles oficiales cargados')
        ->assertSeeText('Los registros sin vinculación válida quedan como inconsistencias')
        ->assertSeeText('Guardar y aplicar goles válidos')
        ->assertDontSeeText('Solo visual')
        ->assertDontSeeText('No impacta estadísticas')
        ->assertDontSeeText('no modifica goles ni estadísticas oficiales de jugadores');
});

it('no muestra acciones masivas de asistencia en la planilla', function () {
    $contexto = crearContextoPlanillaConfirmacion();

    $this->actingAs($contexto['admin'])
        ->get(route('planilla.show', [
            'partidoId' => $contexto['partido']->id,
            'idEdicion' => $contexto['edicion']->id,
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'horario' => '09:00',
        ]))
        ->assertOk()
        ->assertDontSee('Marcar asistencia a todos', false)
        ->assertDontSee('Desmarcar asistencia a todos', false);
});

it('guarda goles arbitrales, aplica los vinculados sobre la planilla oficial y marca inconsistencias', function () {
    $contexto = crearContextoPlanillaConfirmacion();
    $contexto['planilla']->update([
        'asistio' => true,
    ]);
    $contexto['partido']->update([
        'goles_confirmados_at' => now(),
    ]);

    postPlanillaConCsrf(
        $this->actingAs($contexto['admin']),
        route('planilla.actualizarGolesArbitros'),
        array_merge(parametrosBasePlanilla($contexto), [
            'idEquipo' => $contexto['equipoLocal']->id,
            'reportes' => [
                ['numero_camiseta' => 10, 'goles_reportados' => 2],
                ['numero_camiseta' => 77, 'goles_reportados' => 1],
            ],
        ])
    )
        ->assertRedirect()
        ->assertSessionHas('status', 'Goles arbitrales guardados y aplicados sobre la planilla oficial del equipo.')
        ->assertSessionHas('warning', fn (string $warning): bool => str_contains($warning, 'Advertencia: la suma de goles individuales del equipo (2) difiere del resultado oficial (0)'));

    $reportes = GolArbitroReportado::query()
        ->where('partido_id', $contexto['partido']->id)
        ->orderBy('numero_camiseta')
        ->get();

    expect($reportes)->toHaveCount(2);
    expect($reportes[0]->numero_camiseta)->toBe(10);
    expect($reportes[0]->goles_reportados)->toBe(2);
    expect($reportes[0]->idPlanillaJugador)->toBe($contexto['planilla']->id);
    expect($reportes[0]->es_inconsistencia)->toBeFalse();

    expect($reportes[1]->numero_camiseta)->toBe(77);
    expect($reportes[1]->goles_reportados)->toBe(1);
    expect($reportes[1]->idPlanillaJugador)->toBeNull();
    expect($reportes[1]->es_inconsistencia)->toBeTrue();

    expect((int) $contexto['planilla']->fresh()->goles)->toBe(2);
    expect((int) $contexto['jugador']->fresh()->goles_totales)->toBe(2);
    expect(TablaGoleador::query()
        ->where('dni_jugador', $contexto['jugador']->dni)
        ->where('idEdicion', $contexto['edicion']->id)
        ->where('idEquipo', $contexto['equipoLocal']->id)
        ->value('cantidadGoles'))->toBe(2);
    expect($contexto['partido']->fresh()->goles_confirmados_at)->toBeNull();

    $this->actingAs($contexto['admin'])
        ->get(route('planilla.show', [
            'partidoId' => $contexto['partido']->id,
            'idEdicion' => $contexto['edicion']->id,
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'horario' => '09:00',
        ]))
        ->assertOk()
        ->assertSee('Planilla arbitral', false)
        ->assertSee('Dato oficial: 0 gol(es)', false)
        ->assertSee('Reportado por arbitros: 3 gol(es)', false)
        ->assertSee('Camiseta 77', false)
        ->assertSee('El número de camiseta N° 77 reportado por los árbitros no figura en la planilla oficial de este equipo. El gol no se vinculó a ningún jugador.', false);
});

it('marca inconsistencia cuando la camiseta existe pero el jugador no tiene asistencia', function () {
    $contexto = crearContextoPlanillaConfirmacion();
    $contexto['partido']->update([
        'goles_confirmados_at' => now(),
    ]);
    $confirmacionAntes = $contexto['partido']->fresh()->goles_confirmados_at->toIso8601String();

    postPlanillaConCsrf(
        $this->actingAs($contexto['admin']),
        route('planilla.actualizarGolesArbitros'),
        array_merge(parametrosBasePlanilla($contexto), [
            'idEquipo' => $contexto['equipoLocal']->id,
            'reportes' => [
                ['numero_camiseta' => 10, 'goles_reportados' => 1],
            ],
        ])
    )
        ->assertRedirect()
        ->assertSessionHas('status', 'Goles arbitrales guardados. No hubo cambios aplicables en la planilla oficial.')
        ->assertSessionMissing('warning');

    $reporte = GolArbitroReportado::query()
        ->where('partido_id', $contexto['partido']->id)
        ->where('idEquipo', $contexto['equipoLocal']->id)
        ->first();

    expect($reporte)->not->toBeNull();
    expect($reporte->numero_camiseta)->toBe(10);
    expect($reporte->idPlanillaJugador)->toBeNull();
    expect($reporte->es_inconsistencia)->toBeTrue();
    expect($reporte->motivo_inconsistencia)->toBe('El jugador con la camiseta N° 10 figura en la planilla pero no está marcado como presente en este partido, por eso el gol no se vinculó.');

    expect((int) $contexto['planilla']->fresh()->goles)->toBe(0);
    expect((int) $contexto['jugador']->fresh()->goles_totales)->toBe(0);
    expect($contexto['partido']->fresh()->goles_confirmados_at->toIso8601String())->toBe($confirmacionAntes);
    expect(TablaGoleador::query()->where('dni_jugador', $contexto['jugador']->dni)->exists())->toBeFalse();

    $this->actingAs($contexto['admin'])
        ->get(route('planilla.show', [
            'partidoId' => $contexto['partido']->id,
            'idEdicion' => $contexto['edicion']->id,
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'horario' => '09:00',
        ]))
        ->assertOk()
        ->assertSee('Camiseta 10', false)
        ->assertSee('Inconsistencia', false)
        ->assertSee('El jugador con la camiseta N° 10 figura en la planilla pero no está marcado como presente en este partido, por eso el gol no se vinculó.', false)
        ->assertDontSee('Jugador oficial vinculado: Confirmado, Juan', false);
});

it('aplica goles arbitrales al guardar cuando no hay goles oficiales y bloquea la reaplicacion manual', function () {
    $contexto = crearContextoPlanillaConfirmacion();
    $contexto['planilla']->update(['asistio' => true, 'goles' => 0]);

    postPlanillaConCsrf(
        $this->actingAs($contexto['admin']),
        route('planilla.actualizarGolesArbitros'),
        array_merge(parametrosBasePlanilla($contexto), [
            'idEquipo' => $contexto['equipoLocal']->id,
            'reportes' => [
                ['numero_camiseta' => 10, 'goles_reportados' => 3],
            ],
        ])
    )
        ->assertRedirect()
        ->assertSessionHas('status', 'Goles arbitrales guardados y aplicados sobre la planilla oficial del equipo.');

    expect((int) $contexto['planilla']->fresh()->goles)->toBe(3);
    expect((int) $contexto['jugador']->fresh()->goles_totales)->toBe(3);
    expect(TablaGoleador::query()
        ->where('dni_jugador', $contexto['jugador']->dni)
        ->where('idEdicion', $contexto['edicion']->id)
        ->where('idEquipo', $contexto['equipoLocal']->id)
        ->value('cantidadGoles'))->toBe(3);

    postPlanillaConCsrf(
        $this->actingAs($contexto['admin']),
        route('planilla.aplicarGolesArbitrales'),
        array_merge(parametrosBasePlanilla($contexto), [
            'idEquipo' => $contexto['equipoLocal']->id,
        ])
    )
        ->assertRedirect()
        ->assertSessionHasErrors('idEquipo');

    expect((int) $contexto['planilla']->fresh()->goles)->toBe(3);
    expect((int) $contexto['jugador']->fresh()->goles_totales)->toBe(3);
});

it('guarda el reporte arbitral sin tocar goles oficiales existentes y bloquea la aplicacion manual', function () {
    $contexto = crearContextoPlanillaConfirmacion();
    $contexto['planilla']->update(['asistio' => true, 'goles' => 1]);
    $contexto['partido']->update(['goles_confirmados_at' => now()]);

    postPlanillaConCsrf(
        $this->actingAs($contexto['admin']),
        route('planilla.actualizarGolesArbitros'),
        array_merge(parametrosBasePlanilla($contexto), [
            'idEquipo' => $contexto['equipoLocal']->id,
            'reportes' => [
                ['numero_camiseta' => 10, 'goles_reportados' => 2],
            ],
        ])
    )
        ->assertRedirect()
        ->assertSessionHas('status', 'Goles arbitrales guardados. No se aplicaron goles a la planilla oficial porque el equipo ya tiene goles oficiales cargados.')
        ->assertSessionMissing('warning');

    $reporte = GolArbitroReportado::query()
        ->where('partido_id', $contexto['partido']->id)
        ->where('idEquipo', $contexto['equipoLocal']->id)
        ->first();

    expect($reporte)->not->toBeNull();
    expect((int) $reporte->goles_reportados)->toBe(2);
    expect($reporte->idPlanillaJugador)->toBe($contexto['planilla']->id);
    expect($reporte->es_inconsistencia)->toBeFalse();

    expect((int) $contexto['planilla']->fresh()->goles)->toBe(1);
    expect((int) $contexto['jugador']->fresh()->goles_totales)->toBe(0);
    expect($contexto['partido']->fresh()->goles_confirmados_at)->not->toBeNull();

    postPlanillaConCsrf(
        $this->actingAs($contexto['admin']),
        route('planilla.aplicarGolesArbitrales'),
        array_merge(parametrosBasePlanilla($contexto), [
            'idEquipo' => $contexto['equipoLocal']->id,
        ])
    )
        ->assertRedirect()
        ->assertSessionHasErrors('idEquipo');

    expect((int) $contexto['planilla']->fresh()->goles)->toBe(1);
});

it('no duplica goles ni estadisticas al guardar dos veces el mismo reporte arbitral', function () {
    $contexto = crearContextoPlanillaConfirmacion();
    $contexto['planilla']->update(['asistio' => true]);

    $parametros = array_merge(parametrosBasePlanilla($contexto), [
        'idEquipo' => $contexto['equipoLocal']->id,
        'reportes' => [
            ['numero_camiseta' => 10, 'goles_reportados' => 2],
        ],
    ]);

    postPlanillaConCsrf($this->actingAs($contexto['admin']), route('planilla.actualizarGolesArbitros'), $parametros)
        ->assertRedirect();

    postPlanillaConCsrf($this->actingAs($contexto['admin']), route('planilla.actualizarGolesArbitros'), $parametros)
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Goles arbitrales guardados. No se aplicaron goles a la planilla oficial porque el equipo ya tiene goles oficiales cargados.');

    expect((int) $contexto['planilla']->fresh()->goles)->toBe(2);
    expect((int) $contexto['jugador']->fresh()->goles_totales)->toBe(2);
    expect(TablaGoleador::query()
        ->where('dni_jugador', $contexto['jugador']->dni)
        ->where('idEdicion', $contexto['edicion']->id)
        ->where('idEquipo', $contexto['equipoLocal']->id)
        ->value('cantidadGoles'))->toBe(2);
    expect(GolArbitroReportado::query()->where('partido_id', $contexto['partido']->id)->count())->toBe(1);
});

it('no aplica goles cuando la camiseta reportada es ambigua entre jugadores presentes', function () {
    $contexto = crearContextoPlanillaConfirmacion();
    $contexto['planilla']->update(['asistio' => true]);

    $jugadorDuplicado = Jugador::create([
        'dni' => '40500004',
        'nombre' => 'Marco',
        'apellido' => 'Duplicado',
        'goles_totales' => 0,
        'partidos_totales' => 0,
        'tarjetas_rojas_totales' => 0,
    ]);

    PlanillaJugador::create([
        'partido_id' => $contexto['partido']->id,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'dni_jugador' => $jugadorDuplicado->dni,
        'idEquipo' => $contexto['equipoLocal']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'numero_camiseta' => 10,
        'fecha_nacimiento' => '2010-05-01',
        'goles' => 0,
        'asistio' => true,
        'tarjeta_roja' => false,
    ]);

    postPlanillaConCsrf(
        $this->actingAs($contexto['admin']),
        route('planilla.actualizarGolesArbitros'),
        array_merge(parametrosBasePlanilla($contexto), [
            'idEquipo' => $contexto['equipoLocal']->id,
            'reportes' => [
                ['numero_camiseta' => 10, 'goles_reportados' => 2],
            ],
        ])
    )
        ->assertRedirect()
        ->assertSessionHas('status', 'Goles arbitrales guardados. No hubo cambios aplicables en la planilla oficial.');

    $reporte = GolArbitroReportado::query()
        ->where('partido_id', $contexto['partido']->id)
        ->where('idEquipo', $contexto['equipoLocal']->id)
        ->first();

    expect($reporte->es_inconsistencia)->toBeFalse();
    expect($reporte->idPlanillaJugador)->not->toBeNull();

    expect((int) $contexto['planilla']->fresh()->goles)->toBe(0);
    expect((int) PlanillaJugador::query()->where('dni_jugador', $jugadorDuplicado->dni)->value('goles'))->toBe(0);
    expect((int) $contexto['jugador']->fresh()->goles_totales)->toBe(0);
    expect((int) $jugadorDuplicado->fresh()->goles_totales)->toBe(0);
});

it('aplica los goles arbitrales solo al equipo reportado', function () {
    $contexto = crearContextoPlanillaConfirmacion();
    $contexto['planilla']->update(['asistio' => true]);

    $jugadorVisitante = Jugador::create([
        'dni' => '40500002',
        'nombre' => 'Pedro',
        'apellido' => 'Visitante',
        'goles_totales' => 0,
        'partidos_totales' => 0,
        'tarjetas_rojas_totales' => 0,
    ]);

    $planillaVisitante = PlanillaJugador::create([
        'partido_id' => $contexto['partido']->id,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'dni_jugador' => $jugadorVisitante->dni,
        'idEquipo' => $contexto['equipoVisitante']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'numero_camiseta' => 10,
        'fecha_nacimiento' => '2010-03-01',
        'goles' => 0,
        'asistio' => true,
        'tarjeta_roja' => false,
    ]);

    postPlanillaConCsrf(
        $this->actingAs($contexto['admin']),
        route('planilla.actualizarGolesArbitros'),
        array_merge(parametrosBasePlanilla($contexto), [
            'idEquipo' => $contexto['equipoLocal']->id,
            'reportes' => [
                ['numero_camiseta' => 10, 'goles_reportados' => 2],
            ],
        ])
    )->assertRedirect();

    expect((int) $contexto['planilla']->fresh()->goles)->toBe(2);
    expect((int) $planillaVisitante->fresh()->goles)->toBe(0);
    expect((int) $jugadorVisitante->fresh()->goles_totales)->toBe(0);
});
