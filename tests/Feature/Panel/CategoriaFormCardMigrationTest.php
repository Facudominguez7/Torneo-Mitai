<?php

// Feature tests for the Categorías create/edit form migration onto
// x-ui.form-card (T3, panel-admin-redesign). Parity: same fields
// (nombreCategoria, jugadores_maximos), same routes — only the wrapper
// markup and label color change.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\User;

function crearAdminCategoriaFormCard(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

it('renderiza el formulario de alta de categoría dentro de x-ui.form-card', function () {
    $admin = crearAdminCategoriaFormCard();
    $edicion = Edicion::create(['nombre' => 'Edicion Categoria Form Alta']);

    $response = $this->actingAs($admin)->get(route('categoria.create', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('max-w-3xl', false)
        ->assertSee('name="nombreCategoria"', false)
        ->assertSee('name="jugadores_maximos"', false)
        ->assertDontSee('block text-base font-medium text-white', false);
});

it('sigue permitiendo crear una categoría con el mismo contrato de datos', function () {
    $admin = crearAdminCategoriaFormCard();
    $edicion = Edicion::create(['nombre' => 'Edicion Categoria Form Store']);

    // El contenedor de testing expone APP_ENV=local, así que el middleware
    // CSRF valida de verdad (ver PartidoCrudTest::cabecerasCsrf); se inyecta
    // el token explícitamente para la petición mutante.
    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'token-csrf-categoria-formcard'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-categoria-formcard'])
        ->post(route('categoria.store'), [
            'nombreCategoria' => 'Sub 21',
            'idEdicion' => $edicion->id,
        ]);

    $response->assertRedirect(route('categoria.index', ['idEdicion' => $edicion->id]));
    expect(Categoria::where('nombreCategoria', 'Sub 21')->exists())->toBeTrue();
});

it('renderiza el formulario de edición de categoría dentro de x-ui.form-card sin labels blancos', function () {
    $admin = crearAdminCategoriaFormCard();
    $edicion = Edicion::create(['nombre' => 'Edicion Categoria Form Edit']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 22']);

    $response = $this->actingAs($admin)->get(route('categoria.edit', $categoria->id));

    $response->assertOk()
        ->assertSee('max-w-3xl', false)
        ->assertSee('Sub 22')
        ->assertDontSee('block text-base font-medium text-white', false);
});

it('renderiza el detalle de categoría con encabezado legible sobre fondo claro', function () {
    $admin = crearAdminCategoriaFormCard();
    $edicion = Edicion::create(['nombre' => 'Edicion Categoria Form Show']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 23']);

    $response = $this->actingAs($admin)->get(route('categoria.show', [
        'categorium' => $categoria->id,
        'idEdicion' => $edicion->id,
    ]));

    $response->assertOk()
        ->assertSee('Detalle de Categoría')
        ->assertDontSee('block text-base font-medium text-white', false);
});
