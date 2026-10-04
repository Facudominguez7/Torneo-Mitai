<?php

// Regresión: el detalle del equipo daba 500 porque show() usaba
// whereHas('ediciones') sobre EquipoEdicion, que no tiene esa relación.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\User;

it('muestra el detalle del equipo con la edicion y la categoria elegidas', function () {
    $admin = User::factory()->create(['rol' => 'admin', 'email_verified_at' => now()]);
    $edicion = Edicion::create(['nombre' => 'Edicion Detalle Equipo']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Categoria Detalle']);
    $equipo = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Equipo Con Detalle', 'foto' => 'e.png']);
    EquipoEdicion::create(['idEquipo' => $equipo->id, 'idEdicion' => $edicion->id, 'idCategoria' => $categoria->id]);

    $this->actingAs($admin)
        ->get(route('equipo.show', ['equipo' => $equipo->id, 'idEdicion' => $edicion->id, 'idCategoria' => $categoria->id]))
        ->assertOk()
        ->assertSee('Equipo Con Detalle');
});

it('muestra el detalle del equipo aunque no llegue la categoria', function () {
    $admin = User::factory()->create(['rol' => 'admin', 'email_verified_at' => now()]);
    $edicion = Edicion::create(['nombre' => 'Edicion Detalle Sin Categoria']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Categoria Sin Filtro']);
    $equipo = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Equipo Sin Filtro', 'foto' => 'e.png']);
    EquipoEdicion::create(['idEquipo' => $equipo->id, 'idEdicion' => $edicion->id, 'idCategoria' => $categoria->id]);

    $this->actingAs($admin)
        ->get(route('equipo.show', ['equipo' => $equipo->id, 'idEdicion' => $edicion->id]))
        ->assertOk()
        ->assertSee('Equipo Sin Filtro');
});
