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
use App\Models\TrazabilidadBajaJugador;
use App\Models\User;
use App\Services\ValidadorReglasJugador;

const TIPO_PARTIDO_BAJA = 'App\\Models\\Partido';

function crearContextoBajaJugador(): array
{
    $admin = User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);

    $edicion = Edicion::create([
        'nombre' => 'Edicion Baja Test',
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

    $equipoOrigen = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Equipo Origen',
        'foto' => 'origen.png',
    ]);

    $equipoDestino = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Equipo Destino',
        'foto' => 'destino.png',
    ]);

    EquipoEdicion::create([
        'idEdicion' => $edicion->id,
        'idEquipo' => $equipoOrigen->id,
        'idCategoria' => $categoria->id,
    ]);

    EquipoEdicion::create([
        'idEdicion' => $edicion->id,
        'idEquipo' => $equipoDestino->id,
        'idCategoria' => $categoria->id,
    ]);

    $partido = Partido::create([
        'idCategoria' => $categoria->id,
        'idFechas' => $fecha->id,
        'idEquipoLocal' => $equipoOrigen->id,
        'idEquipoVisitante' => $equipoDestino->id,
        'idGrupo' => $grupo->id,
        'idEdicion' => $edicion->id,
        'golesEquipoLocal' => 2,
        'golesEquipoVisitante' => 1,
        'cancha' => 1,
        'jugado' => 1,
        'horario_datetime' => '2026-04-10 10:00:00',
    ]);

    return compact('admin', 'edicion', 'categoria', 'equipoOrigen', 'equipoDestino', 'partido');
}

function crearJugadorConHistorialDeBaja(array $contexto, string $dni): array
{
    $jugador = Jugador::create([
        'dni' => $dni,
        'nombre' => 'Mateo',
        'apellido' => 'Suarez',
        'goles_totales' => 3,
        'partidos_totales' => 1,
        'tarjetas_rojas_totales' => 1,
    ]);

    $listaBuenaFe = ListaBuenaFe::create([
        'dni_jugador' => $dni,
        'idEquipo' => $contexto['equipoOrigen']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'fecha_nacimiento' => '2012-06-15',
        'fecha_alta' => now()->subWeek(),
        'activo' => true,
    ]);

    PlanillaJugador::create([
        'partido_id' => $contexto['partido']->id,
        'partido_type' => TIPO_PARTIDO_BAJA,
        'dni_jugador' => $dni,
        'idEquipo' => $contexto['equipoOrigen']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'numero_camiseta' => 9,
        'goles' => 3,
        'asistio' => true,
        'tarjeta_roja' => true,
        'fecha_nacimiento' => '2012-06-15',
    ]);

    TablaGoleador::create([
        'dni_jugador' => $dni,
        'cantidadGoles' => 3,
        'idEquipo' => $contexto['equipoOrigen']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]);

    return compact('jugador', 'listaBuenaFe');
}

it('archiva el historial operativo y libera al jugador para otro equipo al darlo de baja', function () {
    $contexto = crearContextoBajaJugador();
    $registro = crearJugadorConHistorialDeBaja($contexto, '40900111');

    $token = 'test-token-baja';

    $this->actingAs($contexto['admin'])
        ->withSession(['_token' => $token])
        ->from(route('lista-buena-fe.show', [$contexto['equipoOrigen']->id, $contexto['edicion']->id]))
        ->put(route('lista-buena-fe.dar-baja', $registro['listaBuenaFe']->id), [
            '_token' => $token,
            'motivo_baja' => 'Pase autorizado',
        ])
        ->assertRedirect();

    $registro['jugador']->refresh();
    $registro['listaBuenaFe']->refresh();

    expect($registro['listaBuenaFe']->activo)->toBeFalse();
    expect($registro['listaBuenaFe']->motivo_baja)->toBe('Pase autorizado');
    expect($registro['listaBuenaFe']->fecha_baja)->not->toBeNull();
    expect($registro['jugador']->goles_totales)->toBe(0);
    expect($registro['jugador']->partidos_totales)->toBe(0);
    expect($registro['jugador']->tarjetas_rojas_totales)->toBe(0);
    expect(PlanillaJugador::where('dni_jugador', '40900111')->count())->toBe(0);
    expect(TablaGoleador::where('dni_jugador', '40900111')->count())->toBe(0);

    $trazabilidad = TrazabilidadBajaJugador::where('dni_jugador', '40900111')->first();

    expect($trazabilidad)->not->toBeNull();
    expect($trazabilidad->equipo_nombre)->toBe('Equipo Origen');
    expect($trazabilidad->motivo_baja)->toBe('Pase autorizado');
    expect($trazabilidad->total_goles)->toBe(3);
    expect($trazabilidad->total_partidos)->toBe(1);
    expect($trazabilidad->total_tarjetas_rojas)->toBe(1);
    expect($trazabilidad->cantidad_planillas_archivadas)->toBe(1);
    expect($trazabilidad->historial_planillas)->toHaveCount(1);

    $validacion = app(ValidadorReglasJugador::class)->validarJugador(
        '40900111',
        $contexto['equipoDestino']->id,
        $contexto['edicion']->id,
        $contexto['categoria']->id,
        '2012-06-15'
    );

    expect($validacion)->toBeNull();
});

it('muestra la trazabilidad archivada en la consulta administrativa por dni', function () {
    $contexto = crearContextoBajaJugador();
    $registro = crearJugadorConHistorialDeBaja($contexto, '40900112');

    $token = 'test-token-consulta';

    $this->actingAs($contexto['admin'])
        ->withSession(['_token' => $token])
        ->put(route('lista-buena-fe.dar-baja', $registro['listaBuenaFe']->id), [
            '_token' => $token,
            'motivo_baja' => 'Cambio de equipo',
        ])
        ->assertRedirect();

    $this->actingAs($contexto['admin'])
        ->withSession(['_token' => $token])
        ->post(route('jugador.buscar'), [
            '_token' => $token,
            'dni' => '40900112',
            'idEdicion' => $contexto['edicion']->id,
        ])
        ->assertOk()
        ->assertSee('Historial Archivado por Bajas')
        ->assertSee('Equipo Origen')
        ->assertSee('Cambio de equipo')
        ->assertSee('No computa para ese equipo')
        ->assertSee('Goles archivados');
});
