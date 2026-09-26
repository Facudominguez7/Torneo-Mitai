<?php

// Feature tests for the Fases module migration onto the shared x-ui.*
// components (T4, panel-admin-redesign), following the Equipos/Categorías
// pilots (T3). Parity note: the legacy Fase delete form never sent an
// idEdicion hidden field (ControladorFase::destroy reads $request->idEdicion
// unconditionally), so x-ui.confirm-delete here keeps that exact behaviour
// (no `fields` prop) rather than "fixing" a redirect quirk that predates
// this migration — see the T4 report for the bug note.

use App\Models\Edicion;
use App\Models\Fase;
use App\Models\User;

function crearAdminFaseMigration(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

it('renderiza el listado de fases dentro de x-ui.data-table', function () {
    $admin = crearAdminFaseMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Fase DataTable']);
    Fase::create(['nombre' => 'Fase Migration DataTable']);

    $response = $this->actingAs($admin)->get(route('fase.index', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('rounded-xl', false)
        ->assertSee('overflow-x-auto', false)
        ->assertSee('<thead', false)
        ->assertSee('<tbody', false)
        ->assertSee('Fase Migration DataTable');
});

it('renderiza el estado vacío con dirección y la acción principal cuando no hay fases', function () {
    $admin = crearAdminFaseMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Fase Vacia']);

    $response = $this->actingAs($admin)->get(route('fase.index', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('Todavía no hay fases registradas')
        ->assertSee('border-dashed', false)
        ->assertSee('Agregar fase');
});

it('usa x-ui.button para el alta de fase en lugar del botón legado', function () {
    $admin = crearAdminFaseMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Fase Alta']);

    $response = $this->actingAs($admin)->get(route('fase.index', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee(route('fase.create', ['idEdicion' => $edicion->id]), false)
        ->assertSee('Agregar fase')
        ->assertDontSee('bg-gray-800 hover:bg-gray-900', false);
});

it('reemplaza el borrado directo de fase por x-ui.confirm-delete', function () {
    $admin = crearAdminFaseMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Fase Borrado']);
    Fase::create(['nombre' => 'Fase Migration Borrado']);

    $response = $this->actingAs($admin)->get(route('fase.index', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('data-confirm-delete', false)
        ->assertSee('name="_method"', false)
        ->assertSee('value="DELETE"', false);
});

it('sigue permitiendo eliminar una fase enviando DELETE a la ruta original', function () {
    $admin = crearAdminFaseMigration();
    $fase = Fase::create(['nombre' => 'Fase Migration Borrado Real']);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'token-csrf-fase-datatable'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-fase-datatable'])
        ->delete(route('fase.destroy', $fase->id));

    $response->assertRedirect();
    expect(Fase::find($fase->id))->toBeNull();
});

it('renderiza el formulario de alta de fase dentro de x-ui.form-card sin labels blancos', function () {
    $admin = crearAdminFaseMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Fase Form Alta']);

    $response = $this->actingAs($admin)->get(route('fase.create', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('max-w-3xl', false)
        ->assertSee('name="nombre"', false)
        ->assertSee('name="idEdicion"', false)
        ->assertSee('Crear fase')
        ->assertDontSee('block text-base font-medium text-white', false);
});

it('sigue permitiendo crear una fase con el mismo contrato de datos', function () {
    $admin = crearAdminFaseMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Fase Form Store']);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'token-csrf-fase-formcard'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-fase-formcard'])
        ->post(route('fase.store'), [
            'nombre' => 'Fase Migration Store',
            'idEdicion' => $edicion->id,
        ]);

    $response->assertRedirect(route('fase.index', ['idEdicion' => $edicion->id]));
    expect(Fase::where('nombre', 'Fase Migration Store')->exists())->toBeTrue();
});

it('renderiza el formulario de edición de fase dentro de x-ui.form-card sin labels blancos', function () {
    $admin = crearAdminFaseMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Fase Form Edit']);
    $fase = Fase::create(['nombre' => 'Fase Migration Form Edit']);

    $response = $this->actingAs($admin)->get(route('fase.edit', ['fase' => $fase, 'idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('max-w-3xl', false)
        ->assertSee('Fase Migration Form Edit')
        ->assertSee('Guardar cambios')
        ->assertDontSee('block text-base font-medium text-white', false);
});

it('muestra el resumen de errores accesible al enviar el alta de fase sin nombre', function () {
    $admin = crearAdminFaseMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Fase Form Errores']);

    $respuestaInvalida = $this->actingAs($admin)
        ->from(route('fase.create', ['idEdicion' => $edicion->id]))
        ->withSession(['_token' => 'token-csrf-fase-errores'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-fase-errores'])
        ->post(route('fase.store'), [
            'idEdicion' => $edicion->id,
        ]);

    $respuestaInvalida->assertSessionHasErrors(['nombre']);

    $formulario = $this->actingAs($admin)->get(route('fase.create', ['idEdicion' => $edicion->id]));

    $formulario->assertOk()
        ->assertSee('role="alert"', false)
        ->assertSee('Revisá los campos marcados');
});
