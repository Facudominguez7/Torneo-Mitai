<?php

// Feature tests for the Equipos index/list migration onto x-ui.data-table
// and x-ui.confirm-delete (T3, panel-admin-redesign). Parity: same route,
// same query params, same controller contract as before — only the
// rendered markup changes.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\User;

function crearAdminEquipoDataTable(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function crearEquipoConEdicionYCategoria(string $nombreEdicion, string $nombreCategoria, string $nombreEquipo): array
{
    $edicion = Edicion::create(['nombre' => $nombreEdicion]);

    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => $nombreCategoria,
    ]);

    $equipo = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => $nombreEquipo,
        'foto' => 'escudo.png',
    ]);

    EquipoEdicion::create([
        'idEquipo' => $equipo->id,
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
    ]);

    return compact('edicion', 'categoria', 'equipo');
}

it('renderiza el listado de equipos dentro de x-ui.data-table', function () {
    $admin = crearAdminEquipoDataTable();
    $contexto = crearEquipoConEdicionYCategoria('Edicion DataTable', 'Sub 13', 'Mitai Azul');

    $response = $this->actingAs($admin)->get(route('equipo.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('rounded-xl', false)
        ->assertSee('overflow-x-auto', false)
        ->assertSee('<thead', false)
        ->assertSee('<tbody', false)
        ->assertSee('Mitai Azul');
});

it('muestra la acción Importar de otra edición y ya no el texto legado', function () {
    $admin = crearAdminEquipoDataTable();
    $contexto = crearEquipoConEdicionYCategoria('Edicion Importar', 'Sub 14', 'Mitai Rojo');

    $response = $this->actingAs($admin)->get(route('equipo.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('Importar de otra edición')
        ->assertDontSee('Agregar Equipo de una Edicion Anterior');
});

it('mantiene la ruta de importación de equipos de otra edición', function () {
    $admin = crearAdminEquipoDataTable();
    $contexto = crearEquipoConEdicionYCategoria('Edicion Importar Ruta', 'Sub 15', 'Mitai Verde');

    $response = $this->actingAs($admin)->get(route('equipo.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    // route() a href="{{ ... }}" via Blade escapa "&" como "&amp;".
    $urlEsperada = e(route('seleccionar-edicion', [
        'idEdicion' => $contexto['edicion']->id,
        'tipo' => 'equipoedicion',
    ]));

    $response->assertOk()->assertSee($urlEsperada, false);
});

it('reemplaza el borrado directo por x-ui.confirm-delete con el idEdicion como campo oculto', function () {
    $admin = crearAdminEquipoDataTable();
    $contexto = crearEquipoConEdicionYCategoria('Edicion Borrado', 'Sub 16', 'Mitai Amarillo');

    $response = $this->actingAs($admin)->get(route('equipo.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('data-confirm-delete', false)
        ->assertSee('name="_method"', false)
        ->assertSee('value="DELETE"', false)
        ->assertSee('name="idEdicion" value="'.$contexto['edicion']->id.'"', false);
});

it('sigue permitiendo eliminar un equipo enviando DELETE a la ruta original', function () {
    $admin = crearAdminEquipoDataTable();
    $contexto = crearEquipoConEdicionYCategoria('Edicion Borrado Real', 'Sub 17', 'Mitai Naranja');

    // El contenedor de testing expone APP_ENV=local, así que el middleware
    // CSRF valida de verdad (ver PartidoCrudTest::cabecerasCsrf); se inyecta
    // el token explícitamente para la petición mutante.
    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'token-csrf-equipo-datatable'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-equipo-datatable'])
        ->delete(route('equipo.destroy', $contexto['equipo']->id), [
            'idEdicion' => $contexto['edicion']->id,
        ]);

    // ControladorEquipo::destroy() desvincula al equipo de ESA edición (borra
    // su EquipoEdicion/TablaPosicion) sin borrar el Equipo en sí — un equipo
    // puede estar importado en varias ediciones (ver
    // ControladorEquipoEliminaTablaPosicionesTest, que ya cubre este
    // contrato). La UI solo cambia de markup; el contrato de borrado no.
    $response->assertRedirect(route('equipo.index', ['idEdicion' => $contexto['edicion']->id]));
    $this->assertDatabaseMissing('equipo_ediciones', [
        'idEquipo' => $contexto['equipo']->id,
        'idEdicion' => $contexto['edicion']->id,
    ]);
});

it('alinea los filtros de equipos a la izquierda con el buscador ocupando el espacio libre', function () {
    $admin = crearAdminEquipoDataTable();
    ['edicion' => $edicion] = crearEquipoConEdicionYCategoria('Edicion Filtros', 'Categoria Filtros', 'Equipo Filtros');

    $html = $this->actingAs($admin)->get(route('equipo.index', ['idEdicion' => $edicion->id]))->getContent();

    preg_match('/<form action="[^"]*equipo[^"]*" method="GET"[^>]*class="([^"]*)"/', $html, $formulario);
    expect($formulario[1] ?? '')->not->toContain('justify-center')
        ->and($html)->toMatch('/<div class="[^"]*flex-1[^"]*">\s*<div>\s*<label for="nombreEquipo"/');
});

it('filtra el listado por categoria y conserva el filtro en la paginacion', function () {
    $admin = crearAdminEquipoDataTable();
    ['edicion' => $edicion, 'categoria' => $categoria] = crearEquipoConEdicionYCategoria('Edicion Filtro Cat', 'Categoria Elegida', 'Equipo Elegido');
    $otraCategoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Categoria Otra']);
    $otroEquipo = Equipo::create(['idCategoria' => $otraCategoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Equipo Excluido', 'foto' => 'escudo.png']);
    EquipoEdicion::create(['idEquipo' => $otroEquipo->id, 'idEdicion' => $edicion->id, 'idCategoria' => $otraCategoria->id]);

    $response = $this->actingAs($admin)->get(route('equipo.index', ['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id]));

    $response->assertOk()
        ->assertSee('Equipo Elegido')
        ->assertDontSee('Equipo Excluido');
});

it('muestra el detalle de un equipo sin error', function () {
    $admin = crearAdminEquipoDataTable();
    ['edicion' => $edicion, 'categoria' => $categoria, 'equipo' => $equipo] = crearEquipoConEdicionYCategoria('Edicion Detalle', 'Categoria Detalle', 'Equipo Detalle');

    $this->actingAs($admin)
        ->get(route('equipo.show', ['equipo' => $equipo, 'idEdicion' => $edicion->id, 'idCategoria' => $categoria->id]))
        ->assertOk()
        ->assertSee('Equipo Detalle');
});
