<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\User;

function crearAdminEquipoIndexUi(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function crearContextoEquipoIndexUi(string $nombreEdicion, string $nombreCategoria): array
{
    $edicion = Edicion::create(['nombre' => $nombreEdicion]);

    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => $nombreCategoria,
    ]);

    return compact('edicion', 'categoria');
}

it('renderiza filtros y acciones del listado de equipos con componentes x-ui', function () {
    $admin = crearAdminEquipoIndexUi();
    $contexto = crearContextoEquipoIndexUi('Edicion UI Equipos', 'Sub 15');

    $equipo = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => 'Mitai Verde',
        'foto' => 'escudo.png',
    ]);

    EquipoEdicion::create([
        'idEquipo' => $equipo->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]);

    $response = $this->actingAs($admin)->get(route('equipo.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk();

    // x-ui.search: real <input type="search"> with the panel tone treatment,
    // not the legacy plain gray input.
    $response->assertSee('type="search"', false);
    $response->assertSee('focus:border-brand-navy', false);

    // x-ui.select wraps the categoria <option> list with the same brand-navy
    // focus treatment.
    $response->assertSee('focus:border-brand-navy focus:ring-brand-navy/30', false);
    $response->assertSee('Sub 15');

    // x-ui.button variants: success (Editar), primary (Detalle), danger
    // (Eliminar) — the legacy .btn-editar/.btn-detalle/.btn-eliminar
    // classes must be gone from the rendered output.
    $response->assertSee('bg-emerald-600', false);
    $response->assertSee('bg-red-600', false);
    $response->assertDontSee('btn-editar', false);
    $response->assertDontSee('btn-eliminar', false);
    $response->assertDontSee('btn-detalle', false);
    $response->assertDontSee('class="btn ', false);
});

it('renderiza el estado vacio con x-ui.empty-state cuando no hay equipos', function () {
    $admin = crearAdminEquipoIndexUi();
    $contexto = crearContextoEquipoIndexUi('Edicion UI Equipos Vacia', 'Sub 16');

    $response = $this->actingAs($admin)->get(route('equipo.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('No existen registros')
        ->assertSee('border-dashed border-gray-200', false);
});
