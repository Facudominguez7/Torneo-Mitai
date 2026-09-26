<?php

// Feature tests for the equipogrupo create/edit forms migration onto
// x-ui.form-card (T4, panel-admin-redesign) — the forms reachable from
// Grupos ("Agregar equipo a grupo" and each row's "Editar" in grupos.show).
// equipogrupo.index and equipogrupo.show stay out of scope: both controller
// actions are already dead code on main (empty method bodies, no view()
// call), unreachable from any link in the app.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\EquipoGrupo;
use App\Models\Grupo;
use App\Models\User;

function crearAdminEquipoGrupoMigration(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

it('renderiza el formulario de alta de equipo a grupo dentro de x-ui.form-card sin labels blancos', function () {
    $admin = crearAdminEquipoGrupoMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion EquipoGrupo Form Alta']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 16 EquipoGrupo Alta']);
    $grupo = Grupo::create(['nombre' => 'Grupo EquipoGrupo Alta', 'idEdicion' => $edicion->id, 'idCategoria' => $categoria->id]);
    $equipo = Equipo::create([
        'nombre' => 'Equipo Grupo Miembro Alta',
        'foto' => 'escudo.png',
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
    ]);
    EquipoEdicion::create(['idEquipo' => $equipo->id, 'idEdicion' => $edicion->id, 'idCategoria' => $categoria->id]);

    $response = $this->actingAs($admin)->get(route('equipogrupo.create', [
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'idGrupo' => $grupo->id,
    ]));

    $response->assertOk()
        ->assertSee('max-w-3xl', false)
        ->assertSee('name="idEquipo"', false)
        ->assertSee('name="idEdicion"', false)
        ->assertSee('name="idGrupo"', false)
        ->assertSee('name="idCategoria"', false)
        ->assertSee('Equipo Grupo Miembro Alta')
        ->assertSee('Agregar equipo a grupo')
        ->assertDontSee('block text-base font-medium text-white', false)
        ->assertDontSee('border-white/30 bg-white/10', false);
});

it('sigue permitiendo agregar un equipo a un grupo con el mismo contrato de datos', function () {
    $admin = crearAdminEquipoGrupoMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion EquipoGrupo Form Store']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 16 EquipoGrupo Store']);
    $grupo = Grupo::create(['nombre' => 'Grupo EquipoGrupo Store', 'idEdicion' => $edicion->id, 'idCategoria' => $categoria->id]);
    $equipo = Equipo::create([
        'nombre' => 'Equipo Grupo Miembro Store',
        'foto' => 'escudo.png',
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
    ]);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'token-csrf-equipogrupo-formcard'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-equipogrupo-formcard'])
        ->post(route('equipogrupo.store'), [
            'idEquipo' => $equipo->id,
            'idGrupo' => $grupo->id,
            'idEdicion' => $edicion->id,
            'idCategoria' => $categoria->id,
        ]);

    $response->assertRedirect();
    expect(EquipoGrupo::where('idEquipo', $equipo->id)->where('idGrupo', $grupo->id)->exists())->toBeTrue();
});

it('renderiza el formulario de edición de equipo de grupo dentro de x-ui.form-card sin labels blancos', function () {
    $admin = crearAdminEquipoGrupoMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion EquipoGrupo Form Edit']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 16 EquipoGrupo Edit']);
    $grupo = Grupo::create(['nombre' => 'Grupo EquipoGrupo Edit', 'idEdicion' => $edicion->id, 'idCategoria' => $categoria->id]);
    $equipo = Equipo::create([
        'nombre' => 'Equipo Grupo Miembro Edit',
        'foto' => 'escudo.png',
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
    ]);
    $equipoGrupo = EquipoGrupo::create(['idEquipo' => $equipo->id, 'idGrupo' => $grupo->id]);

    $response = $this->actingAs($admin)->get(route('equipogrupo.edit', [
        'equipogrupo' => $equipoGrupo->id,
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'idGrupo' => $grupo->id,
    ]));

    $response->assertOk()
        ->assertSee('max-w-3xl', false)
        ->assertSee('Equipo Grupo Miembro Edit')
        ->assertSee('Guardar cambios')
        ->assertDontSee('block text-base font-medium text-white', false);
});

it('sigue permitiendo actualizar un equipo de grupo con el mismo contrato de datos', function () {
    $admin = crearAdminEquipoGrupoMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion EquipoGrupo Form Update']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 16 EquipoGrupo Update']);
    $grupo = Grupo::create(['nombre' => 'Grupo EquipoGrupo Update', 'idEdicion' => $edicion->id, 'idCategoria' => $categoria->id]);
    $equipo = Equipo::create([
        'nombre' => 'Equipo Grupo Miembro Update',
        'foto' => 'escudo.png',
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
    ]);
    $otroEquipo = Equipo::create([
        'nombre' => 'Otro Equipo Miembro Update',
        'foto' => 'escudo.png',
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
    ]);
    $equipoGrupo = EquipoGrupo::create(['idEquipo' => $equipo->id, 'idGrupo' => $grupo->id]);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'token-csrf-equipogrupo-update'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-equipogrupo-update'])
        ->patch(route('equipogrupo.update', $equipoGrupo->id), [
            'idEquipo' => $otroEquipo->id,
            'idGrupo' => $grupo->id,
            'idEdicion' => $edicion->id,
        ]);

    $response->assertRedirect();
    expect($equipoGrupo->fresh()->idEquipo)->toBe($otroEquipo->id);
});
