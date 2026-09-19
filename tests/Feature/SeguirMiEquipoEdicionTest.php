<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Partido;

function seguirEdicionBase(): array
{
    $edicionOrigen = Edicion::create(['nombre' => 'Edicion Origen Seguir']);
    $categoriaOrigen = Categoria::create(['idEdicion' => $edicionOrigen->id, 'nombreCategoria' => 'Sub 12']);
    $edicion = Edicion::create(['nombre' => 'Edicion Actual Seguir']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 14']);
    $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Grupo A']);

    return compact('edicionOrigen', 'categoriaOrigen', 'edicion', 'categoria', 'grupo');
}

function seguirEdicionTrasladado(array $contexto, string $nombre): Equipo
{
    $equipo = Equipo::create([
        'idCategoria' => $contexto['categoriaOrigen']->id,
        'idEdicion' => $contexto['edicionOrigen']->id,
        'nombre' => $nombre,
        'foto' => 'equipo.png',
    ]);
    EquipoEdicion::create([
        'idEquipo' => $equipo->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]);

    return $equipo;
}

function seguirEdicionDirecto(array $contexto, string $nombre): Equipo
{
    return Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $nombre,
        'foto' => 'equipo.png',
    ]);
}

function seguirEdicionFecha(array $contexto, string $nombre, string $claveEdicion = 'edicion', string $claveCategoria = 'categoria'): Fecha
{
    return Fecha::create([
        'idCategoria' => $contexto[$claveCategoria]->id,
        'idEdicion' => $contexto[$claveEdicion]->id,
        'nombre' => $nombre,
    ]);
}

function seguirEdicionPartido(
    array $contexto,
    string $claveEdicion,
    string $claveCategoria,
    Fecha $fecha,
    Equipo $local,
    Equipo $visitante,
    string $horario,
    int $jugado = 0,
    ?int $golesLocal = null,
    ?int $golesVisitante = null,
    ?int $idGrupo = null,
): Partido {
    return Partido::create([
        'idCategoria' => $contexto[$claveCategoria]->id,
        'idFechas' => $fecha->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'idGrupo' => $idGrupo,
        'idEdicion' => $contexto[$claveEdicion]->id,
        'golesEquipoLocal' => $jugado === 1 ? $golesLocal : null,
        'golesEquipoVisitante' => $jugado === 1 ? $golesVisitante : null,
        'horario_datetime' => $horario,
        'cancha' => 1,
        'jugado' => $jugado,
    ]);
}

it('muestra en el selector al equipo trasladado con la categoria de la edicion actual', function () {
    $contexto = seguirEdicionBase();
    $trasladado = seguirEdicionTrasladado($contexto, 'Union Trasladada');

    $equipos = $this->get(route('home', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->viewData('equiposDeLaEdicion');

    $opcion = $equipos->firstWhere('id', $trasladado->id);

    expect($opcion)->not->toBeNull()
        ->and($opcion['nombre'])->toBe('Union Trasladada')
        ->and($opcion['idCategoria'])->toBe($contexto['categoria']->id)
        ->and($opcion['nombreCategoria'])->toBe($contexto['categoria']->nombreCategoria);
});

it('mantiene en el selector al equipo directo de la edicion sin fila pivote', function () {
    $contexto = seguirEdicionBase();
    $directo = seguirEdicionDirecto($contexto, 'Directo Sin Pivote');

    $equipos = $this->get(route('home', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->viewData('equiposDeLaEdicion');

    $opcion = $equipos->firstWhere('id', $directo->id);

    expect($opcion)->not->toBeNull()
        ->and($opcion['nombre'])->toBe('Directo Sin Pivote')
        ->and($opcion['idCategoria'])->toBe($contexto['categoria']->id);
});

it('no duplica opciones cuando el equipo participa por ambas vias', function () {
    $contexto = seguirEdicionBase();
    $enAmbas = seguirEdicionDirecto($contexto, 'Club Ambas Vias');
    $categoriaB = Categoria::create(['idEdicion' => $contexto['edicion']->id, 'nombreCategoria' => 'Sub 16']);
    EquipoEdicion::create([
        'idEquipo' => $enAmbas->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $categoriaB->id,
    ]);

    $equipos = $this->get(route('home', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->viewData('equiposDeLaEdicion');

    expect($equipos->where('id', $enAmbas->id)->count())->toBe(1)
        ->and($equipos->firstWhere('id', $enAmbas->id)['idCategoria'])->toBe($categoriaB->id);
});

it('resuelve al equipo trasladado con partidos y links de la edicion seleccionada', function () {
    $contexto = seguirEdicionBase();
    $trasladado = seguirEdicionTrasladado($contexto, 'Union Trasladada');
    $rivalActual = seguirEdicionDirecto($contexto, 'Rival Actual');
    $rivalOrigen = Equipo::create([
        'idCategoria' => $contexto['categoriaOrigen']->id,
        'idEdicion' => $contexto['edicionOrigen']->id,
        'nombre' => 'Rival Origen',
        'foto' => 'rival.png',
    ]);

    $fechaOrigen = seguirEdicionFecha($contexto, 'Fecha Origen 9', 'edicionOrigen', 'categoriaOrigen');
    $fechaActual = seguirEdicionFecha($contexto, 'Fecha Actual 3');

    // El partido de la edicion de origen es mas cercano en el tiempo: solo el
    // filtro por edicion seleccionada puede dejarlo afuera.
    seguirEdicionPartido($contexto, 'edicionOrigen', 'categoriaOrigen', $fechaOrigen, $rivalOrigen, $trasladado, now()->addDays(1)->format('Y-m-d H:i:s'));
    seguirEdicionPartido($contexto, 'edicion', 'categoria', $fechaActual, $trasladado, $rivalActual, now()->addDays(3)->format('Y-m-d H:i:s'));

    $respuesta = $this->getJson('/equipo-seguido?'.http_build_query([
        'nombre' => 'Union Trasladada',
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $respuesta->assertOk()->assertJson([
        'disponible' => true,
        'equipo' => ['nombre' => 'Union Trasladada', 'idCategoria' => $contexto['categoria']->id],
        'proximo' => [
            'rival' => 'Rival Actual',
            'esLocal' => true,
            'fecha_nombre' => 'Fecha Actual 3',
        ],
    ]);

    expect($respuesta->json('links.fixture'))->toContain('idEdicion='.$contexto['edicion']->id)
        ->and($respuesta->json('links.tabla'))->toContain('idEdicion='.$contexto['edicion']->id)
        ->and($respuesta->json('links.tabla'))->toContain('idCategoria='.$contexto['categoria']->id);
});

it('resuelve al trasladado cuando llega la categoria de la edicion como filtro', function () {
    $contexto = seguirEdicionBase();
    seguirEdicionTrasladado($contexto, 'Union Trasladada');

    $respuesta = $this->getJson('/equipo-seguido?'.http_build_query([
        'nombre' => 'Union Trasladada',
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]));

    $respuesta->assertOk()->assertJson([
        'disponible' => true,
        'equipo' => ['idCategoria' => $contexto['categoria']->id],
    ]);
});
