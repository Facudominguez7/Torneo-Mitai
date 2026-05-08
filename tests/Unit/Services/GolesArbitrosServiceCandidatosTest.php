<?php

declare(strict_types=1);

use App\Constants\PartidoConstants;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Fecha;
use App\Models\GolArbitroReportado;
use App\Models\Grupo;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use App\Services\GolesArbitrosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('clasifica reportes en aplicables ignorados y ambiguos', function (): void {
    $edicion = Edicion::create(['nombre' => 'Edicion Test']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 16']);
    $fecha = Fecha::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Fecha 1']);
    $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Grupo A']);
    $equipo = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Local', 'foto' => 'l.png']);
    $equipoVisitante = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Visita', 'foto' => 'v.png']);

    $partido = Partido::create([
        'idCategoria' => $categoria->id,
        'idFechas' => $fecha->id,
        'idEquipoLocal' => $equipo->id,
        'idEquipoVisitante' => $equipoVisitante->id,
        'idGrupo' => $grupo->id,
        'idEdicion' => $edicion->id,
        'golesEquipoLocal' => 0,
        'golesEquipoVisitante' => 0,
        'cancha' => 1,
        'jugado' => 1,
        'horario_datetime' => '2026-04-01 10:00:00',
    ]);

    $jugadorA = Jugador::create(['dni' => '41111001', 'nombre' => 'A', 'apellido' => 'Uno', 'goles_totales' => 0, 'partidos_totales' => 0, 'tarjetas_rojas_totales' => 0]);
    $jugadorB = Jugador::create(['dni' => '41111002', 'nombre' => 'B', 'apellido' => 'Dos', 'goles_totales' => 0, 'partidos_totales' => 0, 'tarjetas_rojas_totales' => 0]);
    $jugadorC = Jugador::create(['dni' => '41111003', 'nombre' => 'C', 'apellido' => 'Tres', 'goles_totales' => 0, 'partidos_totales' => 0, 'tarjetas_rojas_totales' => 0]);

    $planillaAplicable = PlanillaJugador::create([
        'partido_id' => $partido->id,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'dni_jugador' => $jugadorA->dni,
        'idEquipo' => $equipo->id,
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'numero_camiseta' => 10,
        'fecha_nacimiento' => '2010-01-01',
        'goles' => 0,
        'asistio' => true,
        'tarjeta_roja' => false,
    ]);

    $planillaAmbiguaA = PlanillaJugador::create([
        'partido_id' => $partido->id,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'dni_jugador' => $jugadorB->dni,
        'idEquipo' => $equipo->id,
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'numero_camiseta' => 7,
        'fecha_nacimiento' => '2010-01-01',
        'goles' => 0,
        'asistio' => true,
        'tarjeta_roja' => false,
    ]);

    $planillaAmbiguaB = PlanillaJugador::create([
        'partido_id' => $partido->id,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'dni_jugador' => $jugadorC->dni,
        'idEquipo' => $equipo->id,
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'numero_camiseta' => 7,
        'fecha_nacimiento' => '2010-01-01',
        'goles' => 0,
        'asistio' => true,
        'tarjeta_roja' => false,
    ]);

    GolArbitroReportado::create([
        'partido_id' => $partido->id,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'idEdicion' => $edicion->id,
        'idEquipo' => $equipo->id,
        'idPlanillaJugador' => $planillaAplicable->id,
        'numero_camiseta' => 10,
        'goles_reportados' => 2,
        'es_inconsistencia' => false,
        'motivo_inconsistencia' => null,
    ]);

    GolArbitroReportado::create([
        'partido_id' => $partido->id,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'idEdicion' => $edicion->id,
        'idEquipo' => $equipo->id,
        'idPlanillaJugador' => null,
        'numero_camiseta' => null,
        'goles_reportados' => 2,
        'es_inconsistencia' => true,
        'motivo_inconsistencia' => 'inconsistencia',
    ]);

    GolArbitroReportado::create([
        'partido_id' => $partido->id,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'idEdicion' => $edicion->id,
        'idEquipo' => $equipo->id,
        'idPlanillaJugador' => $planillaAplicable->id,
        'numero_camiseta' => 10,
        'goles_reportados' => 0,
        'es_inconsistencia' => false,
        'motivo_inconsistencia' => null,
    ]);

    GolArbitroReportado::create([
        'partido_id' => $partido->id,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'idEdicion' => $edicion->id,
        'idEquipo' => $equipo->id,
        'idPlanillaJugador' => $planillaAmbiguaA->id,
        'numero_camiseta' => 7,
        'goles_reportados' => 1,
        'es_inconsistencia' => false,
        'motivo_inconsistencia' => null,
    ]);

    $resultado = app(GolesArbitrosService::class)->obtenerCandidatosAplicables(
        PartidoConstants::TIPO_PARTIDO_NORMAL,
        $partido->id,
        $edicion->id,
        $equipo->id,
    );

    expect($resultado['aplicables'])->toHaveCount(1);
    expect($resultado['ignorados'])->toHaveCount(2);
    expect($resultado['ambiguos'])->toHaveCount(1);
});
