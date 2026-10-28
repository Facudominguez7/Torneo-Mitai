<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Partido;
use App\Models\TablaPosicion;

function seguirEquipoBase(): array
{
    $edicion = Edicion::create(['nombre' => 'Edicion Seguir Equipo']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 14']);
    $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Grupo A']);

    return compact('edicion', 'categoria', 'grupo');
}

function equipoSeguido(array $contexto, string $nombre, ?int $idEdicion = null): Equipo
{
    return Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $idEdicion ?? $contexto['edicion']->id,
        'nombre' => $nombre,
        'foto' => 'equipo.png',
    ]);
}

function fechaSeguido(array $contexto, string $nombre): Fecha
{
    return Fecha::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $nombre,
    ]);
}

function partidoSeguido(
    array $contexto,
    Fecha $fecha,
    Equipo $local,
    Equipo $visitante,
    string $horario,
    int $jugado = 0,
    ?int $golesLocal = null,
    ?int $golesVisitante = null,
): Partido {
    return Partido::create([
        'idCategoria' => $contexto['categoria']->id,
        'idFechas' => $fecha->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'golesEquipoLocal' => $jugado === 1 ? $golesLocal : null,
        'golesEquipoVisitante' => $jugado === 1 ? $golesVisitante : null,
        'horario_datetime' => $horario,
        'cancha' => 1,
        'jugado' => $jugado,
    ]);
}

function seguirEquipoUrl(array $parametros): string
{
    return '/equipo-seguido?'.http_build_query($parametros);
}

it('devuelve proximo y ultimo con rival localia horario marcador y links', function () {
    $contexto = seguirEquipoBase();
    $amigos = equipoSeguido($contexto, 'Amigos F.c.');
    $boca = equipoSeguido($contexto, 'Boca Chicos');
    $river = equipoSeguido($contexto, 'River Junior');
    $pasada = fechaSeguido($contexto, 'Fecha 1');
    $proxima = fechaSeguido($contexto, 'Fecha 5');

    partidoSeguido($contexto, $pasada, $river, $amigos, now()->subDays(7)->format('Y-m-d H:i:s'), 1, 2, 6);
    partidoSeguido($contexto, $proxima, $amigos, $boca, now()->addDays(3)->format('Y-m-d H:i:s'));

    $respuesta = $this->getJson(seguirEquipoUrl([
        'nombre' => 'Amigos F.c.',
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $respuesta->assertOk();

    $datos = $respuesta->assertJson([
        'disponible' => true,
        'equipo' => ['nombre' => 'Amigos F.c.', 'idCategoria' => $contexto['categoria']->id, 'foto' => 'equipo.png'],
        'proximo' => [
            'rival' => 'Boca Chicos',
            'esLocal' => true,
            'fecha_nombre' => 'Fecha 5',
            'cancha' => 'Cancha 1',
        ],
        'ultimo' => [
            'rival' => 'River Junior',
            'esLocal' => false,
            'goles_favor' => 6,
            'goles_contra' => 2,
        ],
    ])->json();

    expect($amigos->id)->toBeInt();

    expect($datos['proximo']['horario'])->toBeString()->not->toBeEmpty()
        ->and($datos['links']['fixture'])->toContain('/fixture')
        ->and($datos['links']['fixture'])->toContain('idEdicion='.$contexto['edicion']->id)
        ->and($datos['links']['fixture'])->toContain('equipo=Amigos%20F.c.')
        ->and($datos['links']['tabla'])->toContain('idCategoria='.$contexto['categoria']->id)
        ->and($datos['links']['tabla'])->toContain('idGrupo='.$contexto['grupo']->id);
});

it('devuelve posicion en su grupo y forma de los ultimos cinco', function () {
    $contexto = seguirEquipoBase();
    $amigos = equipoSeguido($contexto, 'Amigos F.c.');
    $boca = equipoSeguido($contexto, 'Boca Chicos');
    $river = equipoSeguido($contexto, 'River Junior');
    $fecha = fechaSeguido($contexto, 'Fecha 1');

    // Mismos puntos que Boca, pero mejor diferencia: va primero en la tabla.
    TablaPosicion::create([
        'idGrupo' => $contexto['grupo']->id, 'idEquipo' => $amigos->id,
        'idEdicion' => $contexto['edicion']->id, 'puntos' => 6,
        'golesFavor' => 6, 'golesContra' => 3, 'diferenciaGoles' => 3,
        'jugado' => 5, 'ganado' => 2, 'perdido' => 2, 'empatado' => 1,
    ]);
    TablaPosicion::create([
        'idGrupo' => $contexto['grupo']->id, 'idEquipo' => $boca->id,
        'idEdicion' => $contexto['edicion']->id, 'puntos' => 6,
        'golesFavor' => 4, 'golesContra' => 3, 'diferenciaGoles' => 1,
        'jugado' => 5, 'ganado' => 2, 'perdido' => 2, 'empatado' => 1,
    ]);

    // Cronologico: G, E, P, P, G — la forma se lee del mas reciente al mas viejo.
    partidoSeguido($contexto, $fecha, $amigos, $boca, now()->subDays(12)->format('Y-m-d H:i:s'), 1, 2, 0);
    partidoSeguido($contexto, $fecha, $amigos, $river, now()->subDays(10)->format('Y-m-d H:i:s'), 1, 1, 1);
    partidoSeguido($contexto, $fecha, $boca, $amigos, now()->subDays(8)->format('Y-m-d H:i:s'), 1, 2, 0);
    partidoSeguido($contexto, $fecha, $river, $amigos, now()->subDays(5)->format('Y-m-d H:i:s'), 1, 3, 0);
    partidoSeguido($contexto, $fecha, $amigos, $boca, now()->subDays(2)->format('Y-m-d H:i:s'), 1, 3, 1);

    $respuesta = $this->get(seguirEquipoUrl([
        'nombre' => 'Amigos F.c.',
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $respuesta->assertOk();

    expect($respuesta->json('posicion'))->toBe([
        'posicion' => 1,
        'total' => 2,
        'puntos' => 6,
        'diferencia' => 3,
    ]);
    expect($respuesta->json('forma'))->toBe(['G', 'P', 'P', 'E', 'G']);
});

it('devuelve proximo nulo cuando no hay pendientes pero si ultimo', function () {
    $contexto = seguirEquipoBase();
    $equipo = equipoSeguido($contexto, 'Sporting Sur');
    $rival = equipoSeguido($contexto, 'Atletico Norte');
    $pasada = fechaSeguido($contexto, 'Fecha 8');

    partidoSeguido($contexto, $pasada, $equipo, $rival, now()->subDays(3)->format('Y-m-d H:i:s'), 1, 3, 1);

    $respuesta = $this->getJson(seguirEquipoUrl([
        'nombre' => 'Sporting Sur',
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $respuesta->assertOk()->assertJson([
        'disponible' => true,
        'proximo' => null,
        'ultimo' => [
            'rival' => 'Atletico Norte',
            'esLocal' => true,
            'goles_favor' => 3,
            'goles_contra' => 1,
        ],
    ]);
});

it('devuelve proximo y ultimo nulos con links para un equipo sin partidos', function () {
    $contexto = seguirEquipoBase();
    equipoSeguido($contexto, 'Deportivo Lago');

    $respuesta = $this->getJson(seguirEquipoUrl([
        'nombre' => 'Deportivo Lago',
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $respuesta->assertOk()->assertJson([
        'disponible' => true,
        'proximo' => null,
        'ultimo' => null,
        'posicion' => null,
        'forma' => [],
    ]);

    expect($respuesta->json('links.fixture'))->toContain('equipo=Deportivo%20Lago')
        ->and($respuesta->json('links.tabla'))->toContain('idCategoria='.$contexto['categoria']->id)
        ->and($respuesta->json('links.tabla'))->not->toContain('idGrupo');
});

it('responde 200 con disponible false para un nombre inexistente en la edicion', function () {
    $contexto = seguirEquipoBase();

    $this->getJson(seguirEquipoUrl([
        'nombre' => 'Fantasma F.c.',
        'idEdicion' => $contexto['edicion']->id,
    ]))->assertOk()->assertJson(['disponible' => false]);
});

it('resuelve el mismo equipo con minusculas y espaciado distinto', function () {
    $contexto = seguirEquipoBase();
    $amigos = equipoSeguido($contexto, 'Amigos F.c.');

    $respuesta = $this->getJson(seguirEquipoUrl([
        'nombre' => 'amigos f.c.',
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $respuesta->assertOk()->assertJson([
        'disponible' => true,
        'equipo' => ['nombre' => 'Amigos F.c.', 'idCategoria' => $amigos->idCategoria],
    ]);
});

it('triangula: colapsa espaciado doble interno igual que el mutator', function () {
    $contexto = seguirEquipoBase();
    $amigos = equipoSeguido($contexto, 'Amigos F.c.');

    $respuesta = $this->getJson(seguirEquipoUrl([
        'nombre' => 'amigos  f.c.',
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $respuesta->assertOk()->assertJson([
        'disponible' => true,
        'equipo' => ['nombre' => 'Amigos F.c.', 'idCategoria' => $amigos->idCategoria],
    ]);
});

it('responde disponible false para un equipo existente en otra edicion', function () {
    $contexto = seguirEquipoBase();
    equipoSeguido($contexto, 'Union Vieja');
    $otraEdicion = Edicion::create(['nombre' => 'Edicion Otra']);

    $respuesta = $this->getJson(seguirEquipoUrl([
        'nombre' => 'Union Vieja',
        'idEdicion' => $otraEdicion->id,
    ]));

    $respuesta->assertOk()->assertJson(['disponible' => false]);
});

it('responde 422 sin nombre con idEdicion no numerico o inexistente', function () {
    seguirEquipoBase();

    $this->getJson('/equipo-seguido?idEdicion=1')->assertStatus(422);

    $this->getJson(seguirEquipoUrl([
        'nombre' => 'Amigos F.c.',
        'idEdicion' => 'abc',
    ]))->assertStatus(422);

    $this->getJson(seguirEquipoUrl([
        'nombre' => 'Amigos F.c.',
        'idEdicion' => 99999,
    ]))->assertStatus(422);
});

it('permite consultar el endpoint sin sesion iniciada', function () {
    $contexto = seguirEquipoBase();
    equipoSeguido($contexto, 'Los Cedros');

    $this->getJson(seguirEquipoUrl([
        'nombre' => 'Los Cedros',
        'idEdicion' => $contexto['edicion']->id,
    ]))->assertOk()->assertJson(['disponible' => true]);
});

it('resuelve el equipo de la categoria indicada cuando hay homonimos (enmienda #100)', function () {
    $contexto = seguirEquipoBase();
    $categoriaB = Categoria::create(['idEdicion' => $contexto['edicion']->id, 'nombreCategoria' => 'Sub 16']);
    $azulSub14 = equipoSeguido($contexto, 'Deportivo Misiones Azul');
    $azulSub16 = Equipo::create([
        'idCategoria' => $categoriaB->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => 'Deportivo Misiones Azul',
        'foto' => 'equipo.png',
    ]);

    $respuesta = $this->getJson(seguirEquipoUrl([
        'nombre' => 'Deportivo Misiones Azul',
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $categoriaB->id,
    ]));

    $respuesta->assertOk()->assertJson([
        'disponible' => true,
        'equipo' => ['nombre' => 'Deportivo Misiones Azul', 'idCategoria' => $categoriaB->id],
    ]);

    expect($respuesta->json('equipo.idCategoria'))->not->toBe($azulSub14->idCategoria)
        ->and($respuesta->json('equipo.idCategoria'))->toBe($azulSub16->idCategoria);
});

it('sin idCategoria mantiene el primer match por id (retrocompatible)', function () {
    $contexto = seguirEquipoBase();
    $categoriaB = Categoria::create(['idEdicion' => $contexto['edicion']->id, 'nombreCategoria' => 'Sub 16']);
    $primero = equipoSeguido($contexto, 'Deportivo Misiones Azul');
    $otro = Equipo::create([
        'idCategoria' => $categoriaB->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => 'Deportivo Misiones Azul',
        'foto' => 'equipo.png',
    ]);
    expect($otro->id)->toBeInt();

    $respuesta = $this->getJson(seguirEquipoUrl([
        'nombre' => 'Deportivo Misiones Azul',
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $respuesta->assertOk()->assertJson([
        'disponible' => true,
        'equipo' => ['idCategoria' => $primero->idCategoria],
    ]);
});

it('responde 422 con un idCategoria inexistente', function () {
    $contexto = seguirEquipoBase();
    equipoSeguido($contexto, 'Los Lapachos');

    $this->getJson(seguirEquipoUrl([
        'nombre' => 'Los Lapachos',
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => 99999,
    ]))->assertStatus(422);
});
