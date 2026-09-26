<?php

// Feature tests for the Categorías index/list migration onto
// x-ui.data-table and x-ui.confirm-delete (T3, panel-admin-redesign).

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\User;

function crearAdminCategoriaDataTable(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

it('renderiza el listado de categorías dentro de x-ui.data-table', function () {
    $admin = crearAdminCategoriaDataTable();
    $edicion = Edicion::create(['nombre' => 'Edicion Categoria DataTable']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 18']);

    $response = $this->actingAs($admin)->get(route('categoria.index', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('rounded-xl', false)
        ->assertSee('overflow-x-auto', false)
        ->assertSee('<thead', false)
        ->assertSee('<tbody', false)
        ->assertSee('Sub 18');
});

it('renderiza el estado vacío con x-ui.empty-state cuando no hay categorías', function () {
    $admin = crearAdminCategoriaDataTable();
    $edicion = Edicion::create(['nombre' => 'Edicion Categoria Vacia']);

    $response = $this->actingAs($admin)->get(route('categoria.index', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('No existen registros')
        ->assertSee('border-dashed', false);
});

it('usa x-ui.button para el alta de categoría en lugar del botón legado', function () {
    $admin = crearAdminCategoriaDataTable();
    $edicion = Edicion::create(['nombre' => 'Edicion Categoria Alta']);

    $response = $this->actingAs($admin)->get(route('categoria.index', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee(route('categoria.create', ['idEdicion' => $edicion->id]), false)
        ->assertDontSee('bg-gray-800 hover:bg-gray-900', false);
});

it('reemplaza el borrado directo por x-ui.confirm-delete', function () {
    $admin = crearAdminCategoriaDataTable();
    $edicion = Edicion::create(['nombre' => 'Edicion Categoria Borrado']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 19']);

    $response = $this->actingAs($admin)->get(route('categoria.index', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('data-confirm-delete', false)
        ->assertSee('name="_method"', false)
        ->assertSee('value="DELETE"', false);
});

it('sigue permitiendo eliminar una categoría enviando DELETE a la ruta original', function () {
    $admin = crearAdminCategoriaDataTable();
    $edicion = Edicion::create(['nombre' => 'Edicion Categoria Borrado Real']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 20']);

    // El contenedor de testing expone APP_ENV=local, así que el middleware
    // CSRF valida de verdad (ver PartidoCrudTest::cabecerasCsrf); se inyecta
    // el token explícitamente para la petición mutante.
    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'token-csrf-categoria-datatable'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-categoria-datatable'])
        ->delete(route('categoria.destroy', $categoria->id), [
            'idEdicion' => $edicion->id,
        ]);

    $response->assertRedirect(route('categoria.index', ['idEdicion' => $edicion->id]));
    expect(Categoria::find($categoria->id))->toBeNull();
});
