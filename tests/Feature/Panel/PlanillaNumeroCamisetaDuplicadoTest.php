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
use App\Services\ValidadorNumeroCamiseta;

function crearContextoCamisetaDuplicada(): array
{
    $admin = User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);

    $edicion = Edicion::create([
        'nombre' => 'Edicion Camiseta',
    ]);

    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 18',
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
        'nombre' => 'Local Camiseta',
        'foto' => 'local.png',
    ]);

    $equipoVisitante = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Visitante Camiseta',
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
        'horario_datetime' => '2026-05-01 09:00:00',
    ]);

    $jugadorConDiez = Jugador::create([
        'dni' => '40800001',
        'nombre' => 'Diez',
        'apellido' => 'Titular',
        'goles_totales' => 0,
        'partidos_totales' => 0,
        'tarjetas_rojas_totales' => 0,
    ]);

    $jugadorConOcho = Jugador::create([
        'dni' => '40800002',
        'nombre' => 'Ocho',
        'apellido' => 'Suplente',
        'goles_totales' => 0,
        'partidos_totales' => 0,
        'tarjetas_rojas_totales' => 0,
    ]);

    $planillaConDiez = PlanillaJugador::create([
        'partido_id' => $partido->id,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'dni_jugador' => $jugadorConDiez->dni,
        'idEquipo' => $equipoLocal->id,
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'numero_camiseta' => 10,
        'fecha_nacimiento' => '2008-02-01',
        'goles' => 0,
        'asistio' => false,
        'tarjeta_roja' => false,
    ]);

    $planillaConOcho = PlanillaJugador::create([
        'partido_id' => $partido->id,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'dni_jugador' => $jugadorConOcho->dni,
        'idEquipo' => $equipoLocal->id,
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'numero_camiseta' => 8,
        'fecha_nacimiento' => '2008-03-01',
        'goles' => 0,
        'asistio' => false,
        'tarjeta_roja' => false,
    ]);

    return compact(
        'admin',
        'edicion',
        'categoria',
        'equipoLocal',
        'equipoVisitante',
        'partido',
        'jugadorConDiez',
        'jugadorConOcho',
        'planillaConDiez',
        'planillaConOcho',
    );
}

function postAutosaveNumeroCamiseta($testCase, array $contexto, array $jugadores)
{
    $token = 'token-camiseta-test';

    return $testCase
        ->withSession(['_token' => $token])
        ->withHeader('Accept', 'application/json')
        ->post(route('planilla.actualizarJugadores'), [
            'partido_id' => $contexto['partido']->id,
            'idEdicion' => $contexto['edicion']->id,
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'horario' => '09:00',
            'jugadores' => $jugadores,
            '_token' => $token,
        ]);
}

it('rechaza cambiar el numero de un jugador presente a uno que ya tiene otro presente', function () {
    $contexto = crearContextoCamisetaDuplicada();
    $contexto['planillaConDiez']->update(['asistio' => true]);
    $contexto['planillaConOcho']->update(['asistio' => true]);

    $respuesta = postAutosaveNumeroCamiseta(
        $this->actingAs($contexto['admin']),
        $contexto,
        [
            $contexto['jugadorConOcho']->dni => [
                'numero_camiseta' => 10,
                'goles' => 0,
                'asistencia' => '1',
                'tarjeta_roja' => '0',
            ],
        ]
    );

    $respuesta
        ->assertStatus(422)
        ->assertJsonPath('status', 'validation');

    expect((int) $contexto['planillaConOcho']->fresh()->numero_camiseta)->toBe(8);
});

it('rechaza marcar asistencia cuando el numero ya lo tiene otro jugador presente', function () {
    $contexto = crearContextoCamisetaDuplicada();
    $contexto['planillaConDiez']->update(['asistio' => true]);
    $contexto['planillaConOcho']->update(['numero_camiseta' => 10]);

    $respuesta = postAutosaveNumeroCamiseta(
        $this->actingAs($contexto['admin']),
        $contexto,
        [
            $contexto['jugadorConOcho']->dni => [
                'numero_camiseta' => 10,
                'goles' => 0,
                'asistencia' => '1',
                'tarjeta_roja' => '0',
            ],
        ]
    );

    $respuesta
        ->assertStatus(422)
        ->assertJsonPath('status', 'validation');

    expect((bool) $contexto['planillaConOcho']->fresh()->asistio)->toBeFalse();
});

it('permite el mismo numero cuando el otro jugador no tiene asistencia', function () {
    $contexto = crearContextoCamisetaDuplicada();
    $contexto['planillaConOcho']->update(['asistio' => true]);

    postAutosaveNumeroCamiseta(
        $this->actingAs($contexto['admin']),
        $contexto,
        [
            $contexto['jugadorConOcho']->dni => [
                'numero_camiseta' => 10,
                'goles' => 0,
                'asistencia' => '1',
                'tarjeta_roja' => '0',
            ],
        ]
    )->assertSuccessful();

    expect((int) $contexto['planillaConOcho']->fresh()->numero_camiseta)->toBe(10);
});

it('permite editar goles de una fila presente que ya tenia el numero duplicado', function () {
    $contexto = crearContextoCamisetaDuplicada();
    $contexto['planillaConDiez']->update(['asistio' => true]);
    $contexto['planillaConOcho']->update(['asistio' => true, 'numero_camiseta' => 10]);

    postAutosaveNumeroCamiseta(
        $this->actingAs($contexto['admin']),
        $contexto,
        [
            $contexto['jugadorConOcho']->dni => [
                'numero_camiseta' => 10,
                'goles' => 2,
                'asistencia' => '1',
                'tarjeta_roja' => '0',
            ],
        ]
    )->assertSuccessful();

    expect((int) $contexto['planillaConOcho']->fresh()->goles)->toBe(2);
});

it('permite asignar un numero libre a un jugador presente', function () {
    $contexto = crearContextoCamisetaDuplicada();
    $contexto['planillaConOcho']->update(['asistio' => true]);

    postAutosaveNumeroCamiseta(
        $this->actingAs($contexto['admin']),
        $contexto,
        [
            $contexto['jugadorConOcho']->dni => [
                'numero_camiseta' => 9,
                'goles' => 0,
                'asistencia' => '1',
                'tarjeta_roja' => '0',
            ],
        ]
    )->assertSuccessful();

    expect((int) $contexto['planillaConOcho']->fresh()->numero_camiseta)->toBe(9);
});

it('permite el mismo numero en equipos distintos con ambos presentes', function () {
    $contexto = crearContextoCamisetaDuplicada();
    $contexto['planillaConDiez']->update(['asistio' => true]);
    $contexto['planillaConOcho']->update(['asistio' => true]);

    $jugadorVisitante = Jugador::create([
        'dni' => '40800003',
        'nombre' => 'Visitante',
        'apellido' => 'Diez',
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
        'numero_camiseta' => 8,
        'fecha_nacimiento' => '2008-04-01',
        'goles' => 0,
        'asistio' => true,
        'tarjeta_roja' => false,
    ]);

    postAutosaveNumeroCamiseta(
        $this->actingAs($contexto['admin']),
        $contexto,
        [
            $jugadorVisitante->dni => [
                'numero_camiseta' => 10,
                'goles' => 0,
                'asistencia' => '1',
                'tarjeta_roja' => '0',
            ],
        ]
    )->assertSuccessful();

    expect((int) $planillaVisitante->fresh()->numero_camiseta)->toBe(10);
});

it('no bloquea el guardado de un jugador sin asistencia que cambia de numero', function () {
    $contexto = crearContextoCamisetaDuplicada();
    $contexto['planillaConDiez']->update(['asistio' => true]);

    postAutosaveNumeroCamiseta(
        $this->actingAs($contexto['admin']),
        $contexto,
        [
            $contexto['jugadorConOcho']->dni => [
                'numero_camiseta' => 10,
                'goles' => 0,
                'asistencia' => '0',
                'tarjeta_roja' => '0',
            ],
        ]
    )->assertSuccessful();

    expect((int) $contexto['planillaConOcho']->fresh()->numero_camiseta)->toBe(10);
});

it('rechaza el alta manual de un numero que ya tiene un jugador presente', function () {
    $contexto = crearContextoCamisetaDuplicada();
    $contexto['planillaConDiez']->update(['asistio' => true]);
    $token = 'token-camiseta-test';

    $respuesta = $this->actingAs($contexto['admin'])
        ->withSession(['_token' => $token])
        ->post(route('planilla.agregarJugador'), [
            'partido_id' => $contexto['partido']->id,
            'idEdicion' => $contexto['edicion']->id,
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'horario' => '09:00',
            'equipo_id' => $contexto['equipoLocal']->id,
            'numero_camiseta' => 10,
            'dni_jugador' => '40800099',
            'nombre_jugador' => 'Nuevo',
            'apellido_jugador' => 'Jugador',
            'fecha_nacimiento' => '2008-05-01',
            '_token' => $token,
        ]);

    $respuesta
        ->assertRedirect()
        ->assertSessionHas('status', 'El número de camiseta 10 ya está en uso por otro jugador con asistencia marcada en este equipo para este partido.');

    expect(PlanillaJugador::query()->where('dni_jugador', '40800099')->exists())->toBeFalse();
});

it('no considera ocupado un numero de otro tipo de partido con el mismo id', function () {
    $contexto = crearContextoCamisetaDuplicada();
    $contexto['planillaConDiez']->update(['asistio' => true]);

    PlanillaJugador::create([
        'partido_id' => $contexto['partido']->id,
        'partido_type' => PartidoConstants::CLASS_INSTANCIA_FINAL,
        'dni_jugador' => $contexto['jugadorConDiez']->dni,
        'idEquipo' => $contexto['equipoLocal']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'numero_camiseta' => 12,
        'fecha_nacimiento' => '2008-02-01',
        'goles' => 0,
        'asistio' => true,
        'tarjeta_roja' => false,
    ]);

    $validador = app(ValidadorNumeroCamiseta::class);

    expect($validador->numeroOcupado(
        PartidoConstants::CLASS_INSTANCIA_FINAL,
        $contexto['partido']->id,
        $contexto['equipoLocal']->id,
        12,
    ))->toBeTrue();

    expect($validador->numeroOcupado(
        PartidoConstants::CLASS_PARTIDO,
        $contexto['partido']->id,
        $contexto['equipoLocal']->id,
        12,
    ))->toBeFalse();
});

it('permite repetir el numero cero entre jugadores presentes', function () {
    $contexto = crearContextoCamisetaDuplicada();
    $contexto['planillaConDiez']->update(['asistio' => true, 'numero_camiseta' => 0]);
    $contexto['planillaConOcho']->update(['asistio' => true, 'numero_camiseta' => 0]);

    $validador = app(ValidadorNumeroCamiseta::class);

    expect($validador->numeroOcupado(
        PartidoConstants::CLASS_PARTIDO,
        $contexto['partido']->id,
        $contexto['equipoLocal']->id,
        0,
    ))->toBeFalse();
});

it('no considera ocupado el numero de un jugador sin asistencia', function () {
    $contexto = crearContextoCamisetaDuplicada();

    $validador = app(ValidadorNumeroCamiseta::class);

    expect($validador->numeroOcupado(
        PartidoConstants::CLASS_PARTIDO,
        $contexto['partido']->id,
        $contexto['equipoLocal']->id,
        10,
    ))->toBeFalse();
});
