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

    $fecha = Fecha::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Fecha 1',
    ]);

    $grupo = Grupo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Grupo Nombre Largo',
    ]);

    $local = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Local Nombre Largo A4',
        'foto' => 'local.png',
    ]);

    $visitante = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Visitante Nombre Largo A4',
        'foto' => 'visitante.png',
    ]);

    Partido::create([
        'idCategoria' => $categoria->id,
        'idFechas' => $fecha->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'idGrupo' => $grupo->id,
        'idEdicion' => $edicion->id,
        'golesEquipoLocal' => null,
        'golesEquipoVisitante' => null,
        'cancha' => 1,
        'jugado' => 0,
        'horario_datetime' => '2026-04-11 09:00:00',
    ]);

    return compact('admin', 'edicion');
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
