<?php

// Feature tests for the Partidos module migration onto the shared x-ui.*
// components (T5, panel-admin-redesign), following the Equipos/Categorías/
// Fechas/Grupos pilots (T3/T4). Partidos is a card grid, not a table, so it
// does not use x-ui.data-table; it reuses x-ui.page-header, x-ui.select,
// x-ui.search, x-ui.badge, x-ui.filter-chip, x-ui.empty-state and
// x-ui.confirm-delete instead. Covers the header actions, the filters bar
// (ids/names kept for the existing client-side JS), the active-filter chips,
// the match cards (actions + confirm-delete) and the empty state.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Partido;
use App\Models\User;

function crearAdminPartidoMigration(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function crearContextoPartidoMigration(): array
{
    $edicion = Edicion::create(['nombre' => 'Edicion Partido Migration']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 15 Migration']);
    $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Grupo Migration']);
    $fecha = Fecha::create(['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'nombre' => 'Fecha Migration 1']);

    return compact('edicion', 'categoria', 'grupo', 'fecha');
}

function crearPartidoParaMigration(array $contexto, string $local, string $visitante, array $atributos = []): Partido
{
    $equipoLocal = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $local,
        'foto' => 'local.png',
    ]);
    $equipoVisitante = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $visitante,
        'foto' => 'visitante.png',
    ]);

    return Partido::create(array_merge([
        'idCategoria' => $contexto['categoria']->id,
        'idFechas' => $contexto['fecha']->id,
        'idEquipoLocal' => $equipoLocal->id,
        'idEquipoVisitante' => $equipoVisitante->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'horario_datetime' => '2026-04-05 10:00:00',
        'cancha' => 1,
        'jugado' => 0,
    ], $atributos));
}

it('renderiza el encabezado de Partidos con x-ui.page-header y las tres acciones', function () {
    $admin = crearAdminPartidoMigration();
    $contexto = crearContextoPartidoMigration();
    crearPartidoParaMigration($contexto, 'Rio Grande', 'Yerba Buena');

    $response = $this->actingAs($admin)->get(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
    ]));

    $response->assertOk()
        ->assertSee('Partidos')
        ->assertSee('Nuevo partido')
        ->assertSee('Partido interzonal')
        ->assertSee('Imprimir planilla de árbitros')
        ->assertSee('text-2xl font-bold text-gray-800', false);
});

it('no muestra la accion Partido interzonal sin contexto de categoria', function () {
    $admin = crearAdminPartidoMigration();
    $contexto = crearContextoPartidoMigration();

    $response = $this->actingAs($admin)->get(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('Nuevo partido')
        ->assertDontSee('Partido interzonal');
});

it('renderiza los filtros con los ids que usa el script de la pagina', function () {
    $admin = crearAdminPartidoMigration();
    $contexto = crearContextoPartidoMigration();
    crearPartidoParaMigration($contexto, 'Rio Grande', 'Yerba Buena');
    // Un segundo grupo en la misma categoría mantiene visible el filtro de
    // grupo (con un único grupo por categoría, el controlador lo oculta).
    Grupo::create(['idCategoria' => $contexto['categoria']->id, 'idEdicion' => $contexto['edicion']->id, 'nombre' => 'Grupo Migration B']);

    $response = $this->actingAs($admin)->get(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
    ]));

    $response->assertOk()
        ->assertSee('id="buscar-equipo"', false)
        ->assertSee('name="busqueda"', false)
        ->assertSee('id="filtro-fecha-select"', false)
        ->assertSee('id="filtro-grupo-select"', false)
        ->assertSee('id="filtro-categoria-select"', false)
        ->assertSee('id="filtro-horario-select"', false)
        ->assertSee('id="reset-filters"', false)
        ->assertSee('Limpiar todo');

    preg_match('/<button[^>]*id="reset-filters"[^>]*>/', $response->getContent(), $botonLimpiar);
    expect($botonLimpiar[0] ?? '')->not->toContain('bg-red-')
        ->and($botonLimpiar[0] ?? '')->toContain('type="button"');
});

it('muestra chips de filtro activos removibles y los oculta sin filtros', function () {
    $admin = crearAdminPartidoMigration();
    $contexto = crearContextoPartidoMigration();
    crearPartidoParaMigration($contexto, 'Rio Grande', 'Yerba Buena');

    $conFiltros = $this->actingAs($admin)->get(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
        'idFecha' => $contexto['fecha']->id,
        'horario' => '10:00',
    ]));

    $conFiltros->assertOk()
        ->assertSee('Categoría: '.$contexto['categoria']->nombreCategoria)
        ->assertSee('Grupo: '.$contexto['grupo']->nombre)
        ->assertSee('Fecha: '.$contexto['fecha']->nombre)
        ->assertSee('Horario: 10:00 hs');

    $sinFiltros = $this->actingAs($admin)->get(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $sinFiltros->assertOk()
        ->assertDontSee('Categoría: '.$contexto['categoria']->nombreCategoria)
        ->assertDontSee('Grupo: '.$contexto['grupo']->nombre)
        ->assertDontSee('Fecha: '.$contexto['fecha']->nombre)
        ->assertDontSee('Horario: 10:00 hs');
});

it('renderiza cada tarjeta de partido con las cuatro acciones y el borrado con confirmacion', function () {
    $admin = crearAdminPartidoMigration();
    $contexto = crearContextoPartidoMigration();
    $partido = crearPartidoParaMigration($contexto, 'Rio Grande', 'Yerba Buena');

    $response = $this->actingAs($admin)->get(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
    ]));

    $response->assertOk()
        ->assertSee('Cargar resultado')
        ->assertSee('Planillas')
        ->assertSee('Editar')
        ->assertSee('data-confirm-delete', false)
        ->assertSee('name="_method"', false)
        ->assertSee('value="DELETE"', false)
        ->assertSee(e(route('partido.destroy', [
            'partido' => $partido->id,
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'idGrupo' => $contexto['grupo']->id,
        ])), false);
});

it('muestra Editar Resultado cuando el partido ya tiene goles cargados', function () {
    $admin = crearAdminPartidoMigration();
    $contexto = crearContextoPartidoMigration();
    crearPartidoParaMigration($contexto, 'Rio Grande', 'Yerba Buena', [
        'golesEquipoLocal' => 2,
        'golesEquipoVisitante' => 1,
        'jugado' => 1,
    ]);

    $response = $this->actingAs($admin)->get(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
    ]));

    $response->assertOk()
        ->assertSee('Editar resultado')
        ->assertDontSee('Cargar resultado');
});

it('muestra el badge de estado de goles segun confirmacion', function () {
    $admin = crearAdminPartidoMigration();
    $contexto = crearContextoPartidoMigration();
    crearPartidoParaMigration($contexto, 'Rio Grande', 'Yerba Buena', [
        'golesEquipoLocal' => 2,
        'golesEquipoVisitante' => 1,
        'jugado' => 1,
    ]);
    $confirmado = crearPartidoParaMigration($contexto, 'Otro Local', 'Otro Visitante', [
        'golesEquipoLocal' => 3,
        'golesEquipoVisitante' => 0,
        'jugado' => 1,
        'goles_confirmados_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
    ]));

    $response->assertOk()
        ->assertSee('Goles pendientes de carga')
        ->assertSee('Goles confirmados');
});

it('renderiza el estado vacio con direccion y la accion Nuevo partido cuando el filtro no matchea', function () {
    $admin = crearAdminPartidoMigration();
    $contexto = crearContextoPartidoMigration();

    $response = $this->actingAs($admin)->get(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
    ]));

    $response->assertOk()
        ->assertSee('No se encontraron partidos con estos filtros')
        ->assertSee('border-dashed', false)
        ->assertSee('Nuevo partido');
});

it('agrupa las tarjetas por horario cuando el listado esta filtrado a una unica fecha', function () {
    $admin = crearAdminPartidoMigration();
    $contexto = crearContextoPartidoMigration();
    crearPartidoParaMigration($contexto, 'Rio Grande', 'Yerba Buena', ['horario_datetime' => '2026-04-05 10:00:00']);
    crearPartidoParaMigration($contexto, 'Tafi Viejo', 'Monteros', ['horario_datetime' => '2026-04-05 14:00:00']);

    $response = $this->actingAs($admin)->get(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
        'idFecha' => $contexto['fecha']->id,
    ]));

    $response->assertOk()
        ->assertSeeInOrder(['10:00 hs', 'Rio Grande', '14:00 hs', 'Tafi Viejo']);
});

it('no agrupa por horario cuando el listado no esta filtrado a una unica fecha', function () {
    $admin = crearAdminPartidoMigration();
    $contexto = crearContextoPartidoMigration();
    crearPartidoParaMigration($contexto, 'Rio Grande', 'Yerba Buena', ['horario_datetime' => '2026-04-05 10:00:00']);

    $response = $this->actingAs($admin)->get(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
    ]));

    $response->assertOk();
    expect(preg_match('/<h2[^>]*>\s*10:00 hs\s*<\/h2>/', $response->getContent()))->toBe(0);
});

it('agrupa con el mismo horario que muestra cada tarjeta y deja los partidos sin horario al final', function () {
    $admin = crearAdminPartidoMigration();
    $contexto = crearContextoPartidoMigration();
    crearPartidoParaMigration($contexto, 'Sin Hora Local', 'Sin Hora Visita', ['horario_datetime' => null]);
    // horario (texto legacy en 24 h) no es fillable: se carga como en las ediciones viejas.
    crearPartidoParaMigration($contexto, 'Legacy Local', 'Legacy Visita', ['horario_datetime' => '2026-04-05 09:00:00'])
        ->forceFill(['horario' => '17.30'])->save();
    crearPartidoParaMigration($contexto, 'Manana Local', 'Manana Visita', ['horario_datetime' => '2026-04-05 10:00:00']);

    $response = $this->actingAs($admin)->get(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
        'idFecha' => $contexto['fecha']->id,
    ]));

    $response->assertOk();
    // Solo la sección agrupada: el filtro de horario también lista "10:00 hs".
    $html = $response->getContent();
    $grupos = substr($html, (int) strpos($html, '<h2 class="mb-3 text-sm font-semibold uppercase'));

    expect($grupos)->toMatch('/10:00 hs.*Manana Local.*17:30 hs.*Legacy Local.*Sin horario asignado.*Sin Hora Local/s')
        ->and($grupos)->not->toContain('09:00 hs');
});
