<?php

use App\Constants\PartidoConstants;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Institucion;
use App\Models\Jugador;
use App\Models\PlanillaJugador;
use App\Services\ValidadorReglasJugador;

function crearContextoCategoriasJugador(): array
{
    $edicion = Edicion::create([
        'nombre' => 'Edicion Categorias',
        'jugadores_por_planilla' => 30,
    ]);

    $categoria2014 = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Categoria 2014',
    ]);

    $categoria2013 = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Categoria 2013',
    ]);

    $institucionA = Institucion::create([
        'nombre' => 'Club Mitai A',
    ]);

    $institucionB = Institucion::create([
        'nombre' => 'Club Mitai B',
    ]);

    $equipoOrigen2014 = Equipo::create([
        'nombre' => 'Los Guerreros 2014',
        'foto' => 'origen.png',
        'idCategoria' => $categoria2014->id,
        'idEdicion' => $edicion->id,
        'idInstitucion' => $institucionA->id,
    ]);

    $equipoDestino2013MismaInstitucion = Equipo::create([
        'nombre' => 'Los Guerreros 2013',
        'foto' => 'destino-misma.png',
        'idCategoria' => $categoria2013->id,
        'idEdicion' => $edicion->id,
        'idInstitucion' => $institucionA->id,
    ]);

    $equipoDestino2013OtraInstitucion = Equipo::create([
        'nombre' => 'Rivales 2013',
        'foto' => 'destino-otra.png',
        'idCategoria' => $categoria2013->id,
        'idEdicion' => $edicion->id,
        'idInstitucion' => $institucionB->id,
    ]);

    $equipoDestino2014 = Equipo::create([
        'nombre' => 'Los Guerreros 2014 B',
        'foto' => 'destino-2014.png',
        'idCategoria' => $categoria2014->id,
        'idEdicion' => $edicion->id,
        'idInstitucion' => $institucionA->id,
    ]);

    foreach ([
        [$equipoOrigen2014, $categoria2014],
        [$equipoDestino2013MismaInstitucion, $categoria2013],
        [$equipoDestino2013OtraInstitucion, $categoria2013],
        [$equipoDestino2014, $categoria2014],
    ] as [$equipo, $categoria]) {
        EquipoEdicion::create([
            'idEquipo' => $equipo->id,
            'idEdicion' => $edicion->id,
            'idCategoria' => $categoria->id,
        ]);
    }

    $jugador = Jugador::create([
        'dni' => '40500999',
        'nombre' => 'Mateo',
        'apellido' => 'Categoria',
        'goles_totales' => 0,
        'partidos_totales' => 0,
        'tarjetas_rojas_totales' => 0,
    ]);

    PlanillaJugador::create([
        'partido_id' => 101,
        'partido_type' => PartidoConstants::CLASS_PARTIDO,
        'dni_jugador' => $jugador->dni,
        'idEquipo' => $equipoOrigen2014->id,
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria2014->id,
        'numero_camiseta' => 10,
        'fecha_nacimiento' => '2014-05-10',
        'goles' => 0,
        'asistio' => false,
        'tarjeta_roja' => false,
    ]);

    return compact(
        'edicion',
        'categoria2014',
        'categoria2013',
        'equipoOrigen2014',
        'equipoDestino2013MismaInstitucion',
        'equipoDestino2013OtraInstitucion',
        'equipoDestino2014',
        'jugador'
    );
}

it('permite pasar de categoria 2014 a 2013 dentro de la misma institucion', function () {
    $contexto = crearContextoCategoriasJugador();

    $resultado = app(ValidadorReglasJugador::class)->validarSoloCategoriaYEdad(
        $contexto['jugador']->dni,
        $contexto['equipoDestino2013MismaInstitucion']->id,
        $contexto['edicion']->id,
        $contexto['categoria2013']->id,
        '2014-05-10'
    );

    expect($resultado)->toBeNull();
});

it('advierte si pasa de categoria 2014 a 2013 en otra institucion', function () {
    $contexto = crearContextoCategoriasJugador();

    $resultado = app(ValidadorReglasJugador::class)->validarSoloCategoriaYEdad(
        $contexto['jugador']->dni,
        $contexto['equipoDestino2013OtraInstitucion']->id,
        $contexto['edicion']->id,
        $contexto['categoria2013']->id,
        '2014-05-10'
    );

    expect($resultado)->toBeArray()
        ->and($resultado['type'])->toBe('warning')
        ->and($resultado['message'])->toContain('Solo puede subir a una categoria de mayor edad como "Categoria 2013" dentro de la misma institucion.');
});

it('no advierte a un jugador sin historial de categorias en la edicion', function () {
    $contexto = crearContextoCategoriasJugador();

    Jugador::create([
        'dni' => '40501111',
        'nombre' => 'Nuevo',
        'apellido' => 'SinHistorial',
        'goles_totales' => 0,
        'partidos_totales' => 0,
        'tarjetas_rojas_totales' => 0,
    ]);

    $resultado = app(ValidadorReglasJugador::class)->validarSoloCategoriaYEdad(
        '40501111',
        $contexto['equipoDestino2013OtraInstitucion']->id,
        $contexto['edicion']->id,
        $contexto['categoria2013']->id,
        '2014-05-10'
    );

    expect($resultado)->toBeNull();
});

it('bloquea pasar de categoria 2013 a 2014', function () {
    $contexto = crearContextoCategoriasJugador();

    $resultado = app(ValidadorReglasJugador::class)->validarSoloCategoriaYEdad(
        $contexto['jugador']->dni,
        $contexto['equipoDestino2014']->id,
        $contexto['edicion']->id,
        $contexto['categoria2014']->id,
        '2013-05-10'
    );

    expect($resultado)->toBeArray()
        ->and($resultado['type'])->toBe('error')
        ->and($resultado['message'])->toContain('El jugador es mayor que la edad permitida.');
});
