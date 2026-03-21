<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Jugador;
use App\Models\ListaBuenaFe;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use App\Models\TablaGoleador;
use App\Models\User;

const TIPO_PARTIDO_SYNC = 'App\\Models\\Partido';

/**
 * Crea el contexto base para los tests de sincronización de planilla.
 */
function crearContextoSincronizacion(): array
{
    $admin = User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);

    $edicion = Edicion::create([
        'nombre' => 'Edicion Sync Test',
    ]);

    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 14',
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
        'nombre' => 'Equipo Local Sync',
        'foto' => 'escudo.png',
    ]);

    EquipoEdicion::create([
        'idEdicion' => $edicion->id,
        'idEquipo' => $equipoLocal->id,
        'idCategoria' => $categoria->id,
    ]);

    $equipoVisitante = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Equipo Visitante Sync',
        'foto' => 'escudo.png',
    ]);

    EquipoEdicion::create([
        'idEdicion' => $edicion->id,
        'idEquipo' => $equipoVisitante->id,
        'idCategoria' => $categoria->id,
    ]);

    $partido1 = Partido::create([
        'idCategoria' => $categoria->id,
        'idFechas' => $fecha->id,
        'idEquipoLocal' => $equipoLocal->id,
        'idEquipoVisitante' => $equipoVisitante->id,
        'idGrupo' => $grupo->id,
        'idEdicion' => $edicion->id,
        'golesEquipoLocal' => 0,
        'golesEquipoVisitante' => 0,
        'cancha' => 1,
        'jugado' => 0,
        'horario_datetime' => '2026-04-01 10:00:00',
    ]);

    $partido2 = Partido::create([
        'idCategoria' => $categoria->id,
        'idFechas' => $fecha->id,
        'idEquipoLocal' => $equipoLocal->id,
        'idEquipoVisitante' => $equipoVisitante->id,
        'idGrupo' => $grupo->id,
        'idEdicion' => $edicion->id,
        'golesEquipoLocal' => 0,
        'golesEquipoVisitante' => 0,
        'cancha' => 1,
        'jugado' => 0,
        'horario_datetime' => '2026-04-08 10:00:00',
    ]);

    return compact(
        'admin',
        'edicion',
        'categoria',
        'fecha',
        'grupo',
        'equipoLocal',
        'equipoVisitante',
        'partido1',
        'partido2'
    );
}

/**
 * Crea un jugador con planillas en uno o más partidos, sus stats globales y goleador.
 */
function crearJugadorConEstadisticas(
    array $contexto,
    string $dni,
    int $equipoId,
    array $planillas,
    bool $crearGoleador = true
): Jugador {
    $totalGoles = array_sum(array_column($planillas, 'goles'));
    $totalPartidos = count(array_filter($planillas, fn ($p) => $p['asistio']));
    $totalRojas = count(array_filter($planillas, fn ($p) => $p['tarjeta_roja'] ?? false));

    $jugador = Jugador::create([
        'dni' => $dni,
        'nombre' => 'Jugador',
        'apellido' => "Test-{$dni}",
        'goles_totales' => $totalGoles,
        'partidos_totales' => $totalPartidos,
        'tarjetas_rojas_totales' => $totalRojas,
    ]);

    foreach ($planillas as $planillaData) {
        PlanillaJugador::create([
            'partido_id' => $planillaData['partido_id'],
            'partido_type' => TIPO_PARTIDO_SYNC,
            'dni_jugador' => $dni,
            'idEquipo' => $equipoId,
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'numero_camiseta' => $planillaData['numero_camiseta'] ?? 10,
            'goles' => $planillaData['goles'],
            'asistio' => $planillaData['asistio'],
            'tarjeta_roja' => $planillaData['tarjeta_roja'] ?? false,
        ]);
    }

    if ($crearGoleador && $totalGoles > 0) {
        TablaGoleador::create([
            'dni_jugador' => $dni,
            'cantidadGoles' => $totalGoles,
            'idEquipo' => $equipoId,
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
        ]);
    }

    return $jugador;
}

/**
 * Crea una entrada en ListaBuenaFe para un jugador.
 */
function crearListaBuenaFe(array $contexto, string $dni, int $equipoId, bool $activo = true): ListaBuenaFe
{
    return ListaBuenaFe::create([
        'dni_jugador' => $dni,
        'idEquipo' => $equipoId,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'fecha_nacimiento' => '2012-05-15',
        'fecha_alta' => now(),
        'activo' => $activo,
        'fecha_baja' => $activo ? null : now(),
        'motivo_baja' => $activo ? null : 'Dado de baja para test',
    ]);
}

it('limpia estadísticas y planillas cuando un jugador con goles es dado de baja', function () {
    $contexto = crearContextoSincronizacion();
    $equipoId = $contexto['equipoLocal']->id;

    // Jugador con 3 goles y asistencia en partido1
    $jugador = crearJugadorConEstadisticas($contexto, '40100001', $equipoId, [
        [
            'partido_id' => $contexto['partido1']->id,
            'goles' => 3,
            'asistio' => true,
            'tarjeta_roja' => false,
        ],
    ]);

    // Jugador activo en buena fe que debe mantenerse (para que la planilla exista)
    $jugadorActivo = crearJugadorConEstadisticas($contexto, '40100099', $equipoId, [
        [
            'partido_id' => $contexto['partido1']->id,
            'goles' => 0,
            'asistio' => true,
            'tarjeta_roja' => false,
        ],
    ]);
    crearListaBuenaFe($contexto, '40100099', $equipoId, true);

    // El jugador 40100001 está INACTIVO en la Lista de Buena Fe (dado de baja)
    crearListaBuenaFe($contexto, '40100001', $equipoId, false);

    // Verificar estado previo
    expect($jugador->goles_totales)->toBe(3);
    expect($jugador->partidos_totales)->toBe(1);
    expect(TablaGoleador::where('dni_jugador', '40100001')->exists())->toBeTrue();
    expect(PlanillaJugador::where('dni_jugador', '40100001')->count())->toBe(1);

    // Acceder a la planilla del partido1 (dispara sincronizarPlanilla)
    $this->actingAs($contexto['admin'])
        ->get(route('planilla.show', [
            'partidoId' => $contexto['partido1']->id,
            'idEdicion' => $contexto['edicion']->id,
            'tipoPartido' => 'partido',
            'horario' => '10:00',
        ]))
        ->assertOk();

    // Verificar que se limpiaron las estadísticas
    $jugador->refresh();
    expect($jugador->goles_totales)->toBe(0);
    expect($jugador->partidos_totales)->toBe(0);
    expect($jugador->tarjetas_rojas_totales)->toBe(0);

    // Verificar que se eliminó de la tabla de goleadores
    expect(TablaGoleador::where('dni_jugador', '40100001')->exists())->toBeFalse();

    // Verificar que se eliminaron TODAS las planillas
    expect(PlanillaJugador::where('dni_jugador', '40100001')->count())->toBe(0);
});

it('no genera error al dar de baja a un jugador sin goles ni asistencia', function () {
    $contexto = crearContextoSincronizacion();
    $equipoId = $contexto['equipoLocal']->id;

    // Jugador sin goles, sin asistencia
    $jugador = crearJugadorConEstadisticas($contexto, '40200001', $equipoId, [
        [
            'partido_id' => $contexto['partido1']->id,
            'goles' => 0,
            'asistio' => false,
            'tarjeta_roja' => false,
        ],
    ], crearGoleador: false);

    // Jugador activo para que la planilla no quede vacía
    crearJugadorConEstadisticas($contexto, '40200099', $equipoId, [
        [
            'partido_id' => $contexto['partido1']->id,
            'goles' => 0,
            'asistio' => true,
            'tarjeta_roja' => false,
        ],
    ]);
    crearListaBuenaFe($contexto, '40200099', $equipoId, true);

    // El jugador a dar de baja está inactivo
    crearListaBuenaFe($contexto, '40200001', $equipoId, false);

    // Verificar estado previo
    expect($jugador->goles_totales)->toBe(0);
    expect($jugador->partidos_totales)->toBe(0);

    // Acceder a la planilla (no debe lanzar excepción)
    $this->actingAs($contexto['admin'])
        ->get(route('planilla.show', [
            'partidoId' => $contexto['partido1']->id,
            'idEdicion' => $contexto['edicion']->id,
            'tipoPartido' => 'partido',
            'horario' => '10:00',
        ]))
        ->assertOk();

    // Jugador sin stats sigue en 0 (no negativos)
    $jugador->refresh();
    expect($jugador->goles_totales)->toBe(0);
    expect($jugador->partidos_totales)->toBe(0);
    expect($jugador->tarjetas_rojas_totales)->toBe(0);

    // Sus planillas fueron eliminadas
    expect(PlanillaJugador::where('dni_jugador', '40200001')->count())->toBe(0);
});

it('limpia goles de múltiples partidos al dar de baja al jugador', function () {
    $contexto = crearContextoSincronizacion();
    $equipoId = $contexto['equipoLocal']->id;

    // Jugador con goles en DOS partidos distintos
    $jugador = crearJugadorConEstadisticas($contexto, '40300001', $equipoId, [
        [
            'partido_id' => $contexto['partido1']->id,
            'goles' => 2,
            'asistio' => true,
            'tarjeta_roja' => false,
            'numero_camiseta' => 9,
        ],
        [
            'partido_id' => $contexto['partido2']->id,
            'goles' => 1,
            'asistio' => true,
            'tarjeta_roja' => true,
            'numero_camiseta' => 9,
        ],
    ]);

    // Jugador activo para mantener planilla viva
    crearJugadorConEstadisticas($contexto, '40300099', $equipoId, [
        [
            'partido_id' => $contexto['partido1']->id,
            'goles' => 0,
            'asistio' => true,
            'tarjeta_roja' => false,
        ],
    ]);
    crearListaBuenaFe($contexto, '40300099', $equipoId, true);

    // El jugador 40300001 está dado de baja
    crearListaBuenaFe($contexto, '40300001', $equipoId, false);

    // Verificar estado previo
    expect($jugador->goles_totales)->toBe(3);
    expect($jugador->partidos_totales)->toBe(2);
    expect($jugador->tarjetas_rojas_totales)->toBe(1);
    expect(PlanillaJugador::where('dni_jugador', '40300001')->count())->toBe(2);
    expect(TablaGoleador::where('dni_jugador', '40300001')->first()->cantidadGoles)->toBe(3);

    // Acceder a planilla del partido1 (dispara sincronización)
    $this->actingAs($contexto['admin'])
        ->get(route('planilla.show', [
            'partidoId' => $contexto['partido1']->id,
            'idEdicion' => $contexto['edicion']->id,
            'tipoPartido' => 'partido',
            'horario' => '10:00',
        ]))
        ->assertOk();

    // Todo debe haberse limpiado — ambos partidos
    $jugador->refresh();
    expect($jugador->goles_totales)->toBe(0);
    expect($jugador->partidos_totales)->toBe(0);
    expect($jugador->tarjetas_rojas_totales)->toBe(0);

    expect(TablaGoleador::where('dni_jugador', '40300001')->exists())->toBeFalse();
    expect(PlanillaJugador::where('dni_jugador', '40300001')->count())->toBe(0);
});

it('es idempotente — segunda ejecución no causa error ni negativos', function () {
    $contexto = crearContextoSincronizacion();
    $equipoId = $contexto['equipoLocal']->id;

    // Jugador con goles en un partido
    $jugador = crearJugadorConEstadisticas($contexto, '40400001', $equipoId, [
        [
            'partido_id' => $contexto['partido1']->id,
            'goles' => 2,
            'asistio' => true,
            'tarjeta_roja' => false,
        ],
    ]);

    // Jugador activo para mantener planilla viva
    crearJugadorConEstadisticas($contexto, '40400099', $equipoId, [
        [
            'partido_id' => $contexto['partido1']->id,
            'goles' => 0,
            'asistio' => true,
            'tarjeta_roja' => false,
        ],
    ]);
    crearListaBuenaFe($contexto, '40400099', $equipoId, true);

    // Jugador dado de baja
    crearListaBuenaFe($contexto, '40400001', $equipoId, false);

    $routeParams = [
        'partidoId' => $contexto['partido1']->id,
        'idEdicion' => $contexto['edicion']->id,
        'tipoPartido' => 'partido',
        'horario' => '10:00',
    ];

    // Primera ejecución — limpia todo
    $this->actingAs($contexto['admin'])
        ->get(route('planilla.show', $routeParams))
        ->assertOk();

    $jugador->refresh();
    expect($jugador->goles_totales)->toBe(0);
    expect($jugador->partidos_totales)->toBe(0);
    expect(PlanillaJugador::where('dni_jugador', '40400001')->count())->toBe(0);

    // Segunda ejecución — NO debe generar error ni stats negativos
    $this->actingAs($contexto['admin'])
        ->get(route('planilla.show', $routeParams))
        ->assertOk();

    $jugador->refresh();
    expect($jugador->goles_totales)->toBe(0);
    expect($jugador->partidos_totales)->toBe(0);
    expect($jugador->tarjetas_rojas_totales)->toBe(0);
});
