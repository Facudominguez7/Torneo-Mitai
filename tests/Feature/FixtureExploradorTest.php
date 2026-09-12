<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Partido;
use App\Services\FixtureExploradorService;

function fixtureExploradorBase(): array
{
    $edicion = Edicion::create(['nombre' => 'Edicion Fixture Explorador']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 14']);
    $categoriaB = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 16']);
    $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Grupo A']);

    return compact('edicion', 'categoria', 'categoriaB', 'grupo');
}

function fechaExplorador(array $contexto, string $nombre, ?int $idCategoria = null): Fecha
{
    return Fecha::create([
        'idCategoria' => $idCategoria,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $nombre,
    ]);
}

function partidoExplorador(
    array $contexto,
    Fecha $fecha,
    string $horario,
    string $local = 'Leones del Norte',
    string $visitante = 'Tigres del Sur',
    int $jugado = 0,
    ?int $idCategoria = null,
): Partido {
    $equipoLocal = Equipo::create([
        'idCategoria' => $idCategoria ?? $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $local,
        'foto' => 'local.png',
    ]);
    $equipoVisitante = Equipo::create([
        'idCategoria' => $idCategoria ?? $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $visitante,
        'foto' => 'visita.png',
    ]);

    return Partido::create([
        'idCategoria' => $idCategoria ?? $contexto['categoria']->id,
        'idFechas' => $fecha->id,
        'idEquipoLocal' => $equipoLocal->id,
        'idEquipoVisitante' => $equipoVisitante->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'golesEquipoLocal' => $jugado === 1 ? 2 : null,
        'golesEquipoVisitante' => $jugado === 1 ? 1 : null,
        'horario_datetime' => $horario,
        'cancha' => 1,
        'jugado' => $jugado,
    ]);
}

it('resuelve la fecha del proximo partido no jugado como fecha relevante', function () {
    $contexto = fixtureExploradorBase();
    $pasada = fechaExplorador($contexto, 'Fecha 1');
    $proxima = fechaExplorador($contexto, 'Fecha 2');
    $lejana = fechaExplorador($contexto, 'Fecha 3');

    partidoExplorador($contexto, $pasada, now()->subDays(7)->format('Y-m-d H:i:s'), jugado: 1);
    partidoExplorador($contexto, $proxima, now()->addDay()->format('Y-m-d H:i:s'));
    partidoExplorador($contexto, $lejana, now()->addDays(15)->format('Y-m-d H:i:s'));

    $idRelevante = app(FixtureExploradorService::class)
        ->resolverFechaRelevante($contexto['edicion']->id, $contexto['categoria']->id);

    expect($idRelevante)->toBe($proxima->id);
});

it('resuelve la fecha del ultimo partido jugado cuando no hay futuros', function () {
    $contexto = fixtureExploradorBase();
    $vieja = fechaExplorador($contexto, 'Fecha 1');
    $reciente = fechaExplorador($contexto, 'Fecha 2');

    partidoExplorador($contexto, $vieja, now()->subDays(10)->format('Y-m-d H:i:s'), jugado: 1);
    partidoExplorador($contexto, $reciente, now()->subDays(3)->format('Y-m-d H:i:s'), jugado: 1);

    $idRelevante = app(FixtureExploradorService::class)
        ->resolverFechaRelevante($contexto['edicion']->id, $contexto['categoria']->id);

    expect($idRelevante)->toBe($reciente->id);
});

it('resuelve la primera fecha por id cuando no hay partidos cargados', function () {
    $contexto = fixtureExploradorBase();
    $segundaEnCrearse = fechaExplorador($contexto, 'Fecha B');
    $ultimaEnCrearse = fechaExplorador($contexto, 'Fecha A');

    expect($ultimaEnCrearse->id)->toBeGreaterThan($segundaEnCrearse->id);

    $idRelevante = app(FixtureExploradorService::class)
        ->resolverFechaRelevante($contexto['edicion']->id, $contexto['categoria']->id);

    expect($idRelevante)->toBe($segundaEnCrearse->id);
});

it('ordena fechas por primer horario y desempata por id', function () {
    $contexto = fixtureExploradorBase();
    $f1 = fechaExplorador($contexto, 'Fecha 1');
    $f2 = fechaExplorador($contexto, 'Fecha 2');
    $f3 = fechaExplorador($contexto, 'Fecha 3');
    $f4 = fechaExplorador($contexto, 'Fecha 4');

    $horarioCompartido = now()->addDays(5)->format('Y-m-d H:i:s');
    partidoExplorador($contexto, $f2, $horarioCompartido);
    partidoExplorador($contexto, $f1, $horarioCompartido);
    partidoExplorador($contexto, $f3, now()->addDays(10)->format('Y-m-d H:i:s'));

    $ordenadas = app(FixtureExploradorService::class)
        ->ordenarFechas(collect([$f3, $f2, $f4, $f1]), $contexto['edicion']->id, $contexto['categoria']->id);

    expect($ordenadas->pluck('id')->all())->toBe([$f1->id, $f2->id, $f3->id, $f4->id]);
});

it('resuelve la fecha relevante dentro de la categoria pedida', function () {
    $contexto = fixtureExploradorBase();
    $fechaA = fechaExplorador($contexto, 'Fecha A', $contexto['categoria']->id);
    $fechaB = fechaExplorador($contexto, 'Fecha B', $contexto['categoriaB']->id);

    partidoExplorador($contexto, $fechaA, now()->addDays(5)->format('Y-m-d H:i:s'));
    partidoExplorador($contexto, $fechaB, now()->addDay()->format('Y-m-d H:i:s'), idCategoria: $contexto['categoriaB']->id);

    $service = app(FixtureExploradorService::class);

    expect($service->resolverFechaRelevante($contexto['edicion']->id, $contexto['categoria']->id))->toBe($fechaA->id)
        ->and($service->resolverFechaRelevante($contexto['edicion']->id, $contexto['categoriaB']->id))->toBe($fechaB->id);
});

it('renderiza todas las fechas con idFecha=todas', function () {
    $contexto = fixtureExploradorBase();
    $pasada = fechaExplorador($contexto, 'Fecha 1');
    $proxima = fechaExplorador($contexto, 'Fecha 2');

    partidoExplorador($contexto, $pasada, now()->subDays(7)->format('Y-m-d H:i:s'), jugado: 1);
    partidoExplorador($contexto, $proxima, now()->addDay()->format('Y-m-d H:i:s'));
    partidoExplorador($contexto, $proxima, now()->addDay()->addHours(2)->format('Y-m-d H:i:s'), 'Pumas del Centro', 'Halcones del Este');
    partidoExplorador($contexto, $proxima, now()->addDay()->addHours(4)->format('Y-m-d H:i:s'), 'Cóndores del Oeste', 'Lobos del Mar');

    $respuesta = $this->get(route('fixture', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idFecha' => 'todas',
    ]));

    $respuesta->assertOk();

    expect(substr_count($respuesta->getContent(), 'class="partido-card'))->toBe(4);
});

it('responde 200 para el enlace bare del fixture sin idEdicion', function () {
    $this->get(route('fixture'))->assertOk();
});

it('resuelve la primera fecha cuando la categoria no tiene partidos cargados', function () {
    $contexto = fixtureExploradorBase();
    fechaExplorador($contexto, 'Fecha Uno');
    fechaExplorador($contexto, 'Fecha Dos');

    $respuesta = $this->get(route('fixture', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]));

    $respuesta->assertOk();
    $html = $respuesta->getContent();

    expect(substr_count($html, 'class="partido-card'))->toBe(0)
        ->and($html)->toContain('Fecha Uno');
});
