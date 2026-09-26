<?php

// Feature tests for the Grupos module migration onto the shared x-ui.*
// components (T4, panel-admin-redesign), following the Equipos/Categorías
// pilots (T3). Covers the paginated index (with the "Agregar grupo" and
// "Agregar equipo a grupo" entry points to seleccionar-categoria kept
// reachable) and grupos.show, which lists the group's teams with
// edit/delete per team through the equipogrupo routes.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoGrupo;
use App\Models\Grupo;
use App\Models\User;

function crearAdminGrupoMigration(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

it('renderiza el listado de grupos dentro de x-ui.data-table', function () {
    $admin = crearAdminGrupoMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Grupo DataTable']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 16 Grupo']);
    Grupo::create(['nombre' => 'Grupo Migration DataTable', 'idEdicion' => $edicion->id, 'idCategoria' => $categoria->id]);

    $response = $this->actingAs($admin)->get(route('grupos.index', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('rounded-xl', false)
        ->assertSee('overflow-x-auto', false)
        ->assertSee('<thead', false)
        ->assertSee('<tbody', false)
        ->assertSee('Grupo Migration DataTable');
});

it('renderiza el estado vacío con dirección y la acción principal cuando no hay grupos', function () {
    $admin = crearAdminGrupoMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Grupo Vacia']);

    $response = $this->actingAs($admin)->get(route('grupos.index', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('Todavía no hay grupos en esta edición')
        ->assertSee('border-dashed', false)
        ->assertSee('Agregar grupo');
});

it('mantiene los accesos a Agregar grupo y Agregar equipo a grupo hacia seleccionar-categoria', function () {
    $admin = crearAdminGrupoMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Grupo Accesos']);

    $response = $this->actingAs($admin)->get(route('grupos.index', ['idEdicion' => $edicion->id]));

    // Las rutas se comparan escapadas (e()): con dos query params (idEdicion
    // y tipo), Blade renderiza el "&" del querystring como "&amp;".
    $response->assertOk()
        ->assertSee(e(route('seleccionar-categoria', ['idEdicion' => $edicion->id, 'tipo' => 'grupos'])), false)
        ->assertSee(e(route('seleccionar-categoria', ['idEdicion' => $edicion->id, 'tipo' => 'equipogrupo'])), false)
        ->assertSee('Agregar grupo')
        ->assertSee('Agregar equipo a grupo')
        ->assertDontSee('rounded-lg bg-gray-800 px-4', false);
});

it('reemplaza el borrado directo de grupo por x-ui.confirm-delete con el campo idEdicion', function () {
    $admin = crearAdminGrupoMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Grupo Borrado']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 16 Borrado']);
    Grupo::create(['nombre' => 'Grupo Migration Borrado', 'idEdicion' => $edicion->id, 'idCategoria' => $categoria->id]);

    $response = $this->actingAs($admin)->get(route('grupos.index', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('data-confirm-delete', false)
        ->assertSee('name="_method"', false)
        ->assertSee('value="DELETE"', false)
        ->assertSee('name="idEdicion" value="'.$edicion->id.'"', false);
});

it('sigue permitiendo eliminar un grupo enviando DELETE a la ruta original', function () {
    $admin = crearAdminGrupoMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Grupo Borrado Real']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 16 Real']);
    $grupo = Grupo::create(['nombre' => 'Grupo Migration Borrado Real', 'idEdicion' => $edicion->id, 'idCategoria' => $categoria->id]);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'token-csrf-grupo-datatable'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-grupo-datatable'])
        ->delete(route('grupos.destroy', $grupo->id), [
            'idEdicion' => $edicion->id,
        ]);

    $response->assertRedirect(route('grupos.index', ['idEdicion' => $edicion->id]));
    expect(Grupo::find($grupo->id))->toBeNull();
});

it('renderiza el formulario de alta de grupo dentro de x-ui.form-card sin labels blancos', function () {
    $admin = crearAdminGrupoMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Grupo Form Alta']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 16 Form Alta']);

    $response = $this->actingAs($admin)->get(route('grupos.create', [
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
    ]));

    $response->assertOk()
        ->assertSee('max-w-3xl', false)
        ->assertSee('name="nombre"', false)
        ->assertSee('name="idEdicion"', false)
        ->assertSee('name="idCategoria"', false)
        ->assertSee('Crear grupo')
        ->assertDontSee('block text-base font-medium text-white', false);
});

it('sigue permitiendo crear un grupo con el mismo contrato de datos', function () {
    $admin = crearAdminGrupoMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Grupo Form Store']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 16 Form Store']);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'token-csrf-grupo-formcard'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-grupo-formcard'])
        ->post(route('grupos.store'), [
            'nombre' => 'Grupo Migration Store',
            'idEdicion' => $edicion->id,
            'idCategoria' => $categoria->id,
        ]);

    $response->assertRedirect(route('grupos.index', ['idEdicion' => $edicion->id]));
    expect(Grupo::where('nombre', 'Grupo Migration Store')->exists())->toBeTrue();
});

it('renderiza el formulario de edición de grupo dentro de x-ui.form-card sin labels blancos', function () {
    $admin = crearAdminGrupoMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Grupo Form Edit']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 16 Form Edit']);
    $grupo = Grupo::create(['nombre' => 'Grupo Migration Form Edit', 'idEdicion' => $edicion->id, 'idCategoria' => $categoria->id]);

    $response = $this->actingAs($admin)->get(route('grupos.edit', $grupo));

    $response->assertOk()
        ->assertSee('max-w-3xl', false)
        ->assertSee('Grupo Migration Form Edit')
        ->assertSee('Guardar cambios')
        ->assertDontSee('block text-base font-medium text-white', false);
});

it('muestra el resumen de errores accesible al enviar el alta de grupo sin nombre', function () {
    $admin = crearAdminGrupoMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Grupo Form Errores']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 16 Errores']);

    $respuestaInvalida = $this->actingAs($admin)
        ->from(route('grupos.create', ['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id]))
        ->withSession(['_token' => 'token-csrf-grupo-errores'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-grupo-errores'])
        ->post(route('grupos.store'), [
            'idEdicion' => $edicion->id,
            'idCategoria' => $categoria->id,
        ]);

    $respuestaInvalida->assertSessionHasErrors(['nombre']);

    $formulario = $this->actingAs($admin)->get(route('grupos.create', [
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
    ]));

    $formulario->assertOk()
        ->assertSee('role="alert"', false)
        ->assertSee('Revisá los campos marcados');
});

it('renderiza el detalle de grupo listando los equipos con editar/eliminar via equipogrupo', function () {
    $admin = crearAdminGrupoMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Grupo Detalle']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 16 Detalle']);
    $grupo = Grupo::create(['nombre' => 'Grupo Migration Detalle', 'idEdicion' => $edicion->id, 'idCategoria' => $categoria->id]);
    $equipo = Equipo::create([
        'nombre' => 'Equipo Migration Grupo',
        'foto' => 'escudo.png',
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
    ]);
    $equipoGrupo = EquipoGrupo::create(['idEquipo' => $equipo->id, 'idGrupo' => $grupo->id]);

    $response = $this->actingAs($admin)->get(route('grupos.show', ['grupo' => $grupo, 'idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('rounded-xl', false)
        ->assertSee('overflow-x-auto', false)
        ->assertSee('Equipo Migration Grupo')
        ->assertSee(e(route('equipogrupo.edit', [
            'equipogrupo' => $equipoGrupo->id,
            'idEdicion' => $edicion->id,
            'idCategoria' => $categoria->id,
            'idGrupo' => $grupo->id,
        ])), false)
        ->assertSee('data-confirm-delete', false)
        ->assertSee('name="idEdicion" value="'.$edicion->id.'"', false);
});

it('renderiza el estado vacío del detalle de grupo con dirección cuando no tiene equipos', function () {
    $admin = crearAdminGrupoMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Grupo Detalle Vacio']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 16 Detalle Vacio']);
    $grupo = Grupo::create(['nombre' => 'Grupo Migration Detalle Vacio', 'idEdicion' => $edicion->id, 'idCategoria' => $categoria->id]);

    $response = $this->actingAs($admin)->get(route('grupos.show', ['grupo' => $grupo, 'idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('Todavía no hay equipos en este grupo')
        ->assertSee('border-dashed', false);
});
