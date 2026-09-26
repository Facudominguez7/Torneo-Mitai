<?php

// Feature tests for the Copas module migration onto the shared x-ui.*
// components (T4, panel-admin-redesign), following the Equipos/Categorías
// pilots (T3). Parity note: like Fases, the legacy Copa delete form never
// sent an idEdicion hidden field (ControladorCopa::destroy reads
// $request->idEdicion unconditionally), so x-ui.confirm-delete keeps that
// exact behaviour here (no `fields` prop) — see the T4 report bug note.
// copa.show is unreachable from any UI link on main (only ControladorCopa
// blade routes there directly); its content stays untouched (blank
// @section('detalle')) and only gets a smoke test for the 200 response.

use App\Models\Copa;
use App\Models\Edicion;
use App\Models\User;

function crearAdminCopaMigration(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

it('renderiza el listado de copas dentro de x-ui.data-table', function () {
    $admin = crearAdminCopaMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Copa DataTable']);
    Copa::create(['nombre' => 'Copa Migration DataTable']);

    $response = $this->actingAs($admin)->get(route('copa.index', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('rounded-xl', false)
        ->assertSee('overflow-x-auto', false)
        ->assertSee('<thead', false)
        ->assertSee('<tbody', false)
        ->assertSee('Copa Migration DataTable');
});

it('renderiza el estado vacío con dirección y la acción principal cuando no hay copas', function () {
    $admin = crearAdminCopaMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Copa Vacia']);

    $response = $this->actingAs($admin)->get(route('copa.index', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('Todavía no hay copas registradas')
        ->assertSee('border-dashed', false)
        ->assertSee('Agregar copa');
});

it('usa x-ui.button para el alta de copa en lugar del botón legado', function () {
    $admin = crearAdminCopaMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Copa Alta']);

    $response = $this->actingAs($admin)->get(route('copa.index', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee(route('copa.create', ['idEdicion' => $edicion->id]), false)
        ->assertSee('Agregar copa')
        ->assertDontSee('bg-gray-800 hover:bg-gray-900', false);
});

it('reemplaza el borrado directo de copa por x-ui.confirm-delete', function () {
    $admin = crearAdminCopaMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Copa Borrado']);
    Copa::create(['nombre' => 'Copa Migration Borrado']);

    $response = $this->actingAs($admin)->get(route('copa.index', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('data-confirm-delete', false)
        ->assertSee('name="_method"', false)
        ->assertSee('value="DELETE"', false);
});

it('sigue permitiendo eliminar una copa enviando DELETE a la ruta original', function () {
    $admin = crearAdminCopaMigration();
    $copa = Copa::create(['nombre' => 'Copa Migration Borrado Real']);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'token-csrf-copa-datatable'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-copa-datatable'])
        ->delete(route('copa.destroy', $copa->id));

    $response->assertRedirect();
    expect(Copa::find($copa->id))->toBeNull();
});

it('renderiza el formulario de alta de copa dentro de x-ui.form-card sin labels blancos', function () {
    $admin = crearAdminCopaMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Copa Form Alta']);

    $response = $this->actingAs($admin)->get(route('copa.create', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('max-w-3xl', false)
        ->assertSee('name="nombre"', false)
        ->assertSee('name="idEdicion"', false)
        ->assertSee('Crear copa')
        ->assertDontSee('block text-base font-medium text-white', false);
});

it('sigue permitiendo crear una copa con el mismo contrato de datos', function () {
    $admin = crearAdminCopaMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Copa Form Store']);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'token-csrf-copa-formcard'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-copa-formcard'])
        ->post(route('copa.store'), [
            'nombre' => 'Copa Migration Store',
            'idEdicion' => $edicion->id,
        ]);

    $response->assertRedirect(route('copa.index', ['idEdicion' => $edicion->id]));
    expect(Copa::where('nombre', 'Copa Migration Store')->exists())->toBeTrue();
});

it('renderiza el formulario de edición de copa dentro de x-ui.form-card sin labels blancos', function () {
    $admin = crearAdminCopaMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Copa Form Edit']);
    $copa = Copa::create(['nombre' => 'Copa Migration Form Edit']);

    $response = $this->actingAs($admin)->get(route('copa.edit', ['copa' => $copa, 'idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('max-w-3xl', false)
        ->assertSee('Copa Migration Form Edit')
        ->assertSee('Guardar cambios')
        ->assertDontSee('block text-base font-medium text-white', false);
});

it('muestra el resumen de errores accesible al enviar el alta de copa sin nombre', function () {
    $admin = crearAdminCopaMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Copa Form Errores']);

    $respuestaInvalida = $this->actingAs($admin)
        ->from(route('copa.create', ['idEdicion' => $edicion->id]))
        ->withSession(['_token' => 'token-csrf-copa-errores'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-copa-errores'])
        ->post(route('copa.store'), [
            'idEdicion' => $edicion->id,
        ]);

    $respuestaInvalida->assertSessionHasErrors(['nombre']);

    $formulario = $this->actingAs($admin)->get(route('copa.create', ['idEdicion' => $edicion->id]));

    $formulario->assertOk()
        ->assertSee('role="alert"', false)
        ->assertSee('Revisá los campos marcados');
});

it('responde 200 en el detalle de copa (ruta preexistente sin contenido visible)', function () {
    $admin = crearAdminCopaMigration();
    $copa = Copa::create(['nombre' => 'Copa Migration Show']);

    $response = $this->actingAs($admin)->get(route('copa.show', $copa));

    $response->assertOk();
});
