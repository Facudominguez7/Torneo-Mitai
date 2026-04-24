<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Partido;
use App\Models\User;

function crearContextoImpresionFixtureCompleto(): array
{
    $admin = User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);

    $edicion = Edicion::create([
        'nombre' => 'Edicion Fixture A4',
    ]);

    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 13',
    ]);

    $fechaPrimera = Fecha::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Fecha 1',
    ]);

    $fechaSegunda = Fecha::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Fecha 2',
    ]);

    $grupo = Grupo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Grupo Nombre Largo',
    ]);

    $localPrimera = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Local Nombre Largo A4',
        'foto' => 'local.png',
    ]);

    $visitantePrimera = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Visitante Nombre Largo A4',
        'foto' => 'visitante.png',
    ]);

    $localSegunda = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Local Fecha Dos',
        'foto' => 'local-2.png',
    ]);

    $visitanteSegunda = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Visitante Fecha Dos',
        'foto' => 'visitante-2.png',
    ]);

    Partido::create([
        'idCategoria' => $categoria->id,
        'idFechas' => $fechaPrimera->id,
        'idEquipoLocal' => $localPrimera->id,
        'idEquipoVisitante' => $visitantePrimera->id,
        'idGrupo' => $grupo->id,
        'idEdicion' => $edicion->id,
        'golesEquipoLocal' => null,
        'golesEquipoVisitante' => null,
        'cancha' => 1,
        'jugado' => 0,
        'horario_datetime' => '2026-04-11 09:00:00',
    ]);

    Partido::create([
        'idCategoria' => $categoria->id,
        'idFechas' => $fechaSegunda->id,
        'idEquipoLocal' => $localSegunda->id,
        'idEquipoVisitante' => $visitanteSegunda->id,
        'idGrupo' => $grupo->id,
        'idEdicion' => $edicion->id,
        'golesEquipoLocal' => null,
        'golesEquipoVisitante' => null,
        'cancha' => 2,
        'jugado' => 0,
        'horario_datetime' => '2026-04-18 09:00:00',
    ]);

    return compact('admin', 'edicion', 'categoria', 'fechaPrimera', 'fechaSegunda', 'grupo');
}

it('renderiza el fixture completo con estilos de impresion adaptados a A4', function (): void {
    $contexto = crearContextoImpresionFixtureCompleto();

    $response = $this->actingAs($contexto['admin'])
        ->get(route('admin.imprimir-fixture', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('Fixture Completo')
        ->assertSee('size: A4 portrait', false)
        ->assertSee('margin: 10mm', false)
        ->assertSee('table-layout: fixed', false)
        ->assertSee('overflow-wrap: anywhere', false)
        ->assertSee('break-inside: auto', false)
        ->assertSee('display: table-header-group', false)
        ->assertSee('Local Nombre Largo A4');
});

it('abre un modal para seleccionar la fecha a imprimir desde el panel', function (): void {
    $contexto = crearContextoImpresionFixtureCompleto();

    $response = $this->actingAs($contexto['admin'])
        ->get(route('admin', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('fixture-impresion-modal')
        ->assertSee('Seleccionar fecha de impresión')
        ->assertSee('fecha-fixture-impresion')
        ->assertSee('Todas las fechas')
        ->assertSee('Fecha 1')
        ->assertSee('Fecha 2')
        ->assertSee('abrirModalFixtureImpresion', false)
        ->assertSee('cerrarModalFixtureImpresion', false)
        ->assertSee('confirmarImpresionFixture', false)
        ->assertSee('imprimirFixtureCompleto', false);
});

it('filtra la impresion del fixture completo por fecha seleccionada', function (): void {
    $contexto = crearContextoImpresionFixtureCompleto();

    $response = $this->actingAs($contexto['admin'])
        ->get(route('admin.imprimir-fixture', [
            'idEdicion' => $contexto['edicion']->id,
            'idFecha' => $contexto['fechaPrimera']->id,
        ]));

    $response->assertOk()
        ->assertSee('Fecha 1')
        ->assertSee('Local Nombre Largo A4')
        ->assertDontSee('Fecha 2')
        ->assertDontSee('Local Fecha Dos');
});

it('oculta el grupo por linea cuando la categoria tiene un solo grupo', function (): void {
    $contexto = crearContextoImpresionFixtureCompleto();

    $response = $this->actingAs($contexto['admin'])
        ->get(route('admin.imprimir-fixture', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertDontSee('<th style="width: 8%;">Grupo</th>', false)
        ->assertDontSee('Grupo Nombre Largo');
});

it('agrupa partidos por grupo cuando la categoria tiene mas de un grupo', function (): void {
    $contexto = crearContextoImpresionFixtureCompleto();

    $grupoExtra = Grupo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => 'Grupo Extra',
    ]);

    $localExtra = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => 'Local Grupo Extra',
        'foto' => 'local-extra.png',
    ]);

    $visitanteExtra = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => 'Visitante Grupo Extra',
        'foto' => 'visitante-extra.png',
    ]);

    Partido::create([
        'idCategoria' => $contexto['categoria']->id,
        'idFechas' => $contexto['fechaPrimera']->id,
        'idEquipoLocal' => $localExtra->id,
        'idEquipoVisitante' => $visitanteExtra->id,
        'idGrupo' => $grupoExtra->id,
        'idEdicion' => $contexto['edicion']->id,
        'golesEquipoLocal' => null,
        'golesEquipoVisitante' => null,
        'cancha' => 3,
        'jugado' => 0,
        'horario_datetime' => '2026-04-11 10:00:00',
    ]);

    $response = $this->actingAs($contexto['admin'])
        ->get(route('admin.imprimir-fixture', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('Grupo Nombre Largo')
        ->assertSee('Grupo Extra')
        ->assertSee('Local Grupo Extra')
        ->assertDontSee('<th style="width: 8%;">Grupo</th>', false);
});
