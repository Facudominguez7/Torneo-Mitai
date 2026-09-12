<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Partido;

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
        'equipo' => ['nombre' => 'Amigos F.c.', 'idCategoria' => $contexto['categoria']->id],
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
