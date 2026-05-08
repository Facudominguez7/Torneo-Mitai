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
use App\Models\TablaGoleador;
use App\Services\AplicarGolesArbitralesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function crearContextoAplicacionArbitral(): array
{
    $edicion = Edicion::create(['nombre' => 'Edicion Test']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 16']);
    $fecha = Fecha::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Fecha 1']);
    $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Grupo A']);
    $equipoLocal = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Local', 'foto' => 'l.png']);
    $equipoVisitante = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Visitante', 'foto' => 'v.png']);

    $partido = Partido::create([
        'idCategoria' => $categoria->id,
        'idFechas' => $fecha->id,
        'idEquipoLocal' => $equipoLocal->id,
        'idEquipoVisitante' => $equipoVisitante->id,
        'idGrupo' => $grupo->id,
        'idEdicion' => $edicion->id,
        'golesEquipoLocal' => 1,
        'golesEquipoVisitante' => 0,
        'cancha' => 1,
        'jugado' => 1,
        'horario_datetime' => '2026-04-01 10:00:00',
    ]);

    $jugador = Jugador::create([
        'dni' => '42222001',
        'nombre' => 'Juan',
        'apellido' => 'Gol',
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
        'numero_camiseta' => 9,
        'fecha_nacimiento' => '2010-02-01',
        'goles' => 0,
        'asistio' => true,
        'tarjeta_roja' => false,
    ]);

    return compact('edicion', 'categoria', 'equipoLocal', 'partido', 'jugador', 'planilla');
}

it('aplica cuando todos tienen goles oficiales en cero y actualiza estadisticas', function (): void {
    $contexto = crearContextoAplicacionArbitral();

    GolArbitroReportado::create([
        'partido_id' => $contexto['partido']->id,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $contexto['equipoLocal']->id,
        'idPlanillaJugador' => $contexto['planilla']->id,
        'numero_camiseta' => 9,
        'goles_reportados' => 2,
        'es_inconsistencia' => false,
        'motivo_inconsistencia' => null,
    ]);

    $resultado = app(AplicarGolesArbitralesService::class)->aplicarPorEquipo(
        PartidoConstants::TIPO_PARTIDO_NORMAL,
        $contexto['partido']->id,
        $contexto['edicion']->id,
        $contexto['equipoLocal']->id,
    );

    expect($resultado->status)->toBe('ok');
    expect((int) $contexto['planilla']->fresh()->goles)->toBe(2);
    expect((int) $contexto['jugador']->fresh()->goles_totales)->toBe(2);
    expect(TablaGoleador::query()->where('dni_jugador', $contexto['jugador']->dni)->exists())->toBeTrue();
});

it('bloquea cuando existe al menos un gol oficial en el equipo', function (): void {
    $contexto = crearContextoAplicacionArbitral();
    $contexto['planilla']->update(['goles' => 1]);

    $resultado = app(AplicarGolesArbitralesService::class)->aplicarPorEquipo(
        PartidoConstants::TIPO_PARTIDO_NORMAL,
        $contexto['partido']->id,
        $contexto['edicion']->id,
        $contexto['equipoLocal']->id,
    );

    expect($resultado->status)->toBe('bloqueado');
});
