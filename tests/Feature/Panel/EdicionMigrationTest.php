<?php

// Feature tests for the Ediciones module migration onto the shared
// x-ui.* components (T4, panel-admin-redesign), following the Equipos/
// Categorías pilots (T3): x-ui.data-table for the index, x-ui.form-card
// for create/edit, x-ui.confirm-delete for the destroy action, and a
// x-ui.page-header (no more text-white h1) for the detail page.

use App\Models\Edicion;
use App\Models\User;

function crearAdminEdicionMigration(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

it('renderiza el listado de ediciones dentro de x-ui.data-table', function () {
    $admin = crearAdminEdicionMigration();
    Edicion::create(['nombre' => 'Edicion Migration DataTable', 'jugadores_por_planilla' => 20]);

    $response = $this->actingAs($admin)->get(route('edicion.index'));

    $response->assertOk()
        ->assertSee('rounded-xl', false)
        ->assertSee('overflow-x-auto', false)
        ->assertSee('<thead', false)
        ->assertSee('<tbody', false)
        ->assertSee('Edicion Migration DataTable');
});

it('renderiza el estado vacío con dirección y la acción principal cuando no hay ediciones', function () {
    $admin = crearAdminEdicionMigration();

    $response = $this->actingAs($admin)->get(route('edicion.index'));

    $response->assertOk()
        ->assertSee('Todavía no hay ediciones registradas')
        ->assertSee('border-dashed', false)
        ->assertSee('Agregar edición');
});

it('usa x-ui.button para el alta de edición en lugar del botón legado', function () {
    $admin = crearAdminEdicionMigration();

    $response = $this->actingAs($admin)->get(route('edicion.index'));

    $response->assertOk()
        ->assertSee(route('edicion.create'), false)
        ->assertSee('Agregar edición')
        ->assertDontSee('bg-gray-800 hover:bg-gray-900', false);
});

it('reemplaza el borrado directo de edición por x-ui.confirm-delete', function () {
    $admin = crearAdminEdicionMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Migration Borrado', 'jugadores_por_planilla' => 18]);

    $response = $this->actingAs($admin)->get(route('edicion.index'));

    $response->assertOk()
        ->assertSee('data-confirm-delete', false)
        ->assertSee('name="_method"', false)
        ->assertSee('value="DELETE"', false);
});

it('sigue permitiendo eliminar una edición enviando DELETE a la ruta original', function () {
    $admin = crearAdminEdicionMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Migration Borrado Real', 'jugadores_por_planilla' => 18]);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'token-csrf-edicion-datatable'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-edicion-datatable'])
        ->delete(route('edicion.destroy', $edicion->id));

    $response->assertRedirect();
    expect(Edicion::find($edicion->id))->toBeNull();
});

it('renderiza el formulario de alta de edición dentro de x-ui.form-card sin labels blancos', function () {
    $admin = crearAdminEdicionMigration();

    $response = $this->actingAs($admin)->get(route('edicion.create'));

    $response->assertOk()
        ->assertSee('max-w-3xl', false)
        ->assertSee('name="nombre"', false)
        ->assertSee('name="jugadores_por_planilla"', false)
        ->assertSee('Crear edición')
        ->assertDontSee('block text-base font-medium text-white', false);
});

it('sigue permitiendo crear una edición con el mismo contrato de datos', function () {
    $admin = crearAdminEdicionMigration();

    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'token-csrf-edicion-formcard'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-edicion-formcard'])
        ->post(route('edicion.store'), [
            'nombre' => 'Edicion Migration Store',
            'jugadores_por_planilla' => 22,
        ]);

    $response->assertRedirect(route('edicion.index'));
    expect(Edicion::where('nombre', 'Edicion Migration Store')->exists())->toBeTrue();
});

it('renderiza el formulario de edición de edición dentro de x-ui.form-card sin labels blancos', function () {
    $admin = crearAdminEdicionMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Migration Editar', 'jugadores_por_planilla' => 18]);

    $response = $this->actingAs($admin)->get(route('edicion.edit', $edicion));

    $response->assertOk()
        ->assertSee('max-w-3xl', false)
        ->assertSee('Edicion Migration Editar')
        ->assertSee('Guardar cambios')
        ->assertDontSee('block text-base font-medium text-white', false);
});

it('renderiza el detalle de edición con encabezado legible sobre fondo claro', function () {
    $admin = crearAdminEdicionMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Migration Detalle', 'jugadores_por_planilla' => 18]);

    $response = $this->actingAs($admin)->get(route('edicion.show', $edicion));

    $response->assertOk()
        ->assertSee('Detalle de edición')
        ->assertDontSee('text-2xl font-bold text-white', false);
});

it('muestra el resumen de errores accesible al enviar el alta de edición sin nombre', function () {
    $admin = crearAdminEdicionMigration();

    $respuestaInvalida = $this->actingAs($admin)
        ->from(route('edicion.create'))
        ->withSession(['_token' => 'token-csrf-edicion-errores'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-edicion-errores'])
        ->post(route('edicion.store'), [
            'jugadores_por_planilla' => 18,
        ]);

    $respuestaInvalida->assertSessionHasErrors(['nombre']);

    $formulario = $this->actingAs($admin)->get(route('edicion.create'));

    $formulario->assertOk()
        ->assertSee('role="alert"', false)
        ->assertSee('Revisá los campos marcados');
});
