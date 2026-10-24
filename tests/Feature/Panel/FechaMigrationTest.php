<?php

// Feature tests for the Fechas module migration onto the shared x-ui.*
// components (T4, panel-admin-redesign), following the Equipos/Categorías
// pilots (T3): x-ui.data-table for the paginated index, x-ui.form-card for
// create/edit, x-ui.confirm-delete (with the idEdicion hidden field the
// legacy delete form already sent) for the destroy action.

use App\Models\Edicion;
use App\Models\Fecha;
use App\Models\User;

function crearAdminFechaMigration(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

it('renderiza el listado de fechas dentro de x-ui.data-table', function () {
    $admin = crearAdminFechaMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Fecha DataTable']);
    Fecha::create(['idEdicion' => $edicion->id, 'nombre' => 'Fecha 1 Migration']);

    $response = $this->actingAs($admin)->get(route('fecha.index', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('rounded-xl', false)
        ->assertSee('overflow-x-auto', false)
        ->assertSee('<thead', false)
        ->assertSee('<tbody', false)
        ->assertSee('Fecha 1 Migration');
});

it('renderiza el estado vacío con dirección y la acción principal cuando no hay fechas', function () {
    $admin = crearAdminFechaMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Fecha Vacia']);

    $response = $this->actingAs($admin)->get(route('fecha.index', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('Todavía no hay fechas en esta edición')
        ->assertSee('border-dashed', false)
        ->assertSee('Agregar fecha');
});

it('usa x-ui.button para el alta de fecha en lugar del botón legado', function () {
    $admin = crearAdminFechaMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Fecha Alta']);

    $response = $this->actingAs($admin)->get(route('fecha.index', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee(route('fecha.create', ['idEdicion' => $edicion->id]), false)
        ->assertSee('Agregar fecha')
        ->assertDontSee('bg-gray-800 hover:bg-gray-900', false);
});

it('reemplaza el borrado directo de fecha por x-ui.confirm-delete con el campo idEdicion', function () {
    $admin = crearAdminFechaMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Fecha Borrado']);
    $fecha = Fecha::create(['idEdicion' => $edicion->id, 'nombre' => 'Fecha Migration Borrado']);

    $response = $this->actingAs($admin)->get(route('fecha.index', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('data-confirm-delete', false)
        ->assertSee('action="'.e(route('fecha.destroy', $fecha)).'"', false)
        ->assertSee('name="_method"', false)
        ->assertSee('value="DELETE"', false)
        ->assertSee('name="idEdicion" value="'.$edicion->id.'"', false);
});

it('sigue permitiendo eliminar una fecha enviando DELETE a la ruta original', function () {
    $admin = crearAdminFechaMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Fecha Borrado Real']);
    $fecha = Fecha::create(['idEdicion' => $edicion->id, 'nombre' => 'Fecha Migration Borrado Real']);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'token-csrf-fecha-datatable'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-fecha-datatable'])
        ->delete(route('fecha.destroy', $fecha->id), [
            'idEdicion' => $edicion->id,
        ]);

    $response->assertRedirect(route('fecha.index', ['idEdicion' => $edicion->id]));
    expect(Fecha::find($fecha->id))->toBeNull();
});

it('renderiza el formulario de alta de fecha dentro de x-ui.form-card sin labels blancos', function () {
    $admin = crearAdminFechaMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Fecha Form Alta']);

    $response = $this->actingAs($admin)->get(route('fecha.create', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('max-w-3xl', false)
        ->assertSee('name="nombre"', false)
        ->assertSee('name="idEdicion"', false)
        ->assertSee('Crear fecha')
        ->assertDontSee('block text-base font-medium text-white', false);
});

it('sigue permitiendo crear una fecha con el mismo contrato de datos', function () {
    $admin = crearAdminFechaMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Fecha Form Store']);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'token-csrf-fecha-formcard'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-fecha-formcard'])
        ->post(route('fecha.store'), [
            'nombre' => 'Fecha Migration Store',
            'idEdicion' => $edicion->id,
        ]);

    $response->assertRedirect(route('fecha.index', ['idEdicion' => $edicion->id]));
    expect(Fecha::where('nombre', 'Fecha Migration Store')->exists())->toBeTrue();
});

it('renderiza el formulario de edición de fecha dentro de x-ui.form-card sin labels blancos', function () {
    $admin = crearAdminFechaMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Fecha Form Edit']);
    $fecha = Fecha::create(['idEdicion' => $edicion->id, 'nombre' => 'Fecha Migration Form Edit']);

    $response = $this->actingAs($admin)->get(route('fecha.edit', $fecha));

    $response->assertOk()
        ->assertSee('max-w-3xl', false)
        ->assertSee('Fecha Migration Form Edit')
        ->assertSee('Guardar cambios')
        ->assertDontSee('block text-base font-medium text-white', false);
});

it('muestra el resumen de errores accesible al enviar el alta de fecha sin nombre', function () {
    $admin = crearAdminFechaMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Fecha Form Errores']);

    $respuestaInvalida = $this->actingAs($admin)
        ->from(route('fecha.create', ['idEdicion' => $edicion->id]))
        ->withSession(['_token' => 'token-csrf-fecha-errores'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-fecha-errores'])
        ->post(route('fecha.store'), [
            'idEdicion' => $edicion->id,
        ]);

    $respuestaInvalida->assertSessionHasErrors(['nombre']);

    $formulario = $this->actingAs($admin)->get(route('fecha.create', ['idEdicion' => $edicion->id]));

    $formulario->assertOk()
        ->assertSee('role="alert"', false)
        ->assertSee('Revisá los campos marcados');
});

it('pagina el listado de fechas en el pie de la tabla', function () {
    $admin = crearAdminFechaMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Fechas Paginadas']);
    foreach (range(1, 9) as $numero) {
        Fecha::create(['idEdicion' => $edicion->id, 'nombre' => 'Fecha paginada '.$numero]);
    }

    $response = $this->actingAs($admin)->get(route('fecha.index', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('Fecha paginada 9')
        ->assertDontSee('Fecha paginada 1<', false)
        ->assertSee(e(route('fecha.index', ['idEdicion' => $edicion->id, 'page' => 2])), false);
});
