<?php

// Feature tests for the Tabla de goleadores admin module migration onto the
// shared x-ui.* components (T8a, panel-admin-redesign). Covers the canvas
// layout: category chips (built from the $categorias data the controller
// already passed but the old view never rendered), a top-3 podium shown only
// on page 1 of a single-category ranking, the "Corrección manual" action
// (the existing seleccionar-categoria create flow, unchanged) and "Ver en el
// portal" towards the public tabla-goleadores route. The "Ver jugador" row
// action prefills the DNI on jugador.consulta (ControladorConsultaJugador
// reads ?dni= into the search field). No controller/route change: only the
// Panel/tabla_goleador/* views.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\TablaGoleador;
use App\Models\User;

function crearAdminTablaGoleadorMigration(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function crearContextoTablaGoleadorMigration(array $atributos = []): array
{
    $edicion = Edicion::create(['nombre' => $atributos['nombreEdicion'] ?? 'Edicion Goleadores Migration']);
    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => $atributos['nombreCategoria'] ?? 'Sub 14 Migration',
    ]);
    $equipo = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => $atributos['nombreEquipo'] ?? 'Mitai Naranja',
        'foto' => 'escudo.png',
    ]);

    return compact('edicion', 'categoria', 'equipo');
}

function crearGoleadorMigration(array $contexto, string $dni, string $nombre, string $apellido, int $goles): TablaGoleador
{
    Jugador::create([
        'dni' => $dni,
        'nombre' => $nombre,
        'apellido' => $apellido,
        'goles_totales' => $goles,
        'partidos_totales' => 1,
    ]);

    return TablaGoleador::create([
        'idEquipo' => $contexto['equipo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'dni_jugador' => $dni,
        'cantidadGoles' => $goles,
    ]);
}

it('renderiza la correccion manual y el link al portal con la categoria activa', function () {
    $admin = crearAdminTablaGoleadorMigration();
    $contexto = crearContextoTablaGoleadorMigration();
    crearGoleadorMigration($contexto, '40300001', 'Mateo', 'Duarte', 5);

    $response = $this->actingAs($admin)->get(route('tabla_goleador.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]));

    $response->assertOk()
        ->assertSee('Corrección manual')
        ->assertSee(e(route('seleccionar-categoria', ['idEdicion' => $contexto['edicion']->id, 'tipo' => 'tabla_goleador'])), false)
        ->assertSee(e(route('tabla-goleadores', ['idEdicion' => $contexto['edicion']->id, 'idCategoria' => $contexto['categoria']->id])), false)
        ->assertSee('target="_blank"', false)
        ->assertSee('rel="noopener"', false)
        ->assertSee('Ver en el portal')
        ->assertSee('aria-current="page"', false);
});

it('muestra el podio de los primeros tres goleadores de la categoria en la pagina uno', function () {
    $admin = crearAdminTablaGoleadorMigration();
    $contexto = crearContextoTablaGoleadorMigration();

    crearGoleadorMigration($contexto, '40300010', 'Uno', 'Alvarez', 12);
    crearGoleadorMigration($contexto, '40300011', 'Dos', 'Bianchi', 9);
    crearGoleadorMigration($contexto, '40300012', 'Tres', 'Costa', 7);
    crearGoleadorMigration($contexto, '40300013', 'Cuatro', 'Duarte', 5);

    $response = $this->actingAs($admin)->get(route('tabla_goleador.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]));

    $response->assertOk()
        ->assertSee('data-podio-posicion="1"', false)
        ->assertSee('data-podio-posicion="2"', false)
        ->assertSee('data-podio-posicion="3"', false)
        ->assertDontSee('data-podio-posicion="4"', false)
        ->assertSee('data-posicion="4"', false)
        // La columna "Jugador" ya mostraba solo el primer nombre del jugador
        // (select sin alias de `j.nombre` en el controlador); se preserva
        // ese mismo dato, sin agregar el apellido, para no cambiar lo que
        // el admin ve hoy en esa celda.
        ->assertSeeInOrder(['data-podio-posicion="1"', 'Uno'], false);
});

it('no muestra podio cuando no hay categoria seleccionada y conserva la columna de categoria', function () {
    $admin = crearAdminTablaGoleadorMigration();
    $contexto = crearContextoTablaGoleadorMigration();
    crearGoleadorMigration($contexto, '40300020', 'Bruno', 'Sosa', 4);

    $response = $this->actingAs($admin)->get(route('tabla_goleador.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertDontSee('data-podio-posicion', false)
        ->assertSee('Categoría')
        ->assertSee($contexto['categoria']->nombreCategoria);
});

it('el boton ver jugador prellena el dni en la consulta de jugador', function () {
    $admin = crearAdminTablaGoleadorMigration();
    $contexto = crearContextoTablaGoleadorMigration();
    crearGoleadorMigration($contexto, '40300030', 'Enzo', 'Villalba', 8);

    $response = $this->actingAs($admin)->get(route('tabla_goleador.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]));

    $response->assertOk()
        ->assertSee('Ver jugador')
        ->assertSee(e(route('jugador.consulta', ['dni' => '40300030'])), false);

    $consulta = $this->actingAs($admin)->get(route('jugador.consulta', ['dni' => '40300030']));

    $consulta->assertOk()->assertSee('value="40300030"', false);
});

it('elimina un goleador con confirmacion real y conserva el campo oculto idEdicion', function () {
    $admin = crearAdminTablaGoleadorMigration();
    $contexto = crearContextoTablaGoleadorMigration();
    $goleador = crearGoleadorMigration($contexto, '40300040', 'Gael', 'Nuñez', 3);

    $response = $this->actingAs($admin)->get(route('tabla_goleador.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]));

    $response->assertOk()
        ->assertSee('data-confirm-delete', false)
        ->assertSee(e(route('tabla_goleador.destroy', $goleador)), false)
        ->assertSee('name="idEdicion" value="'.$contexto['edicion']->id.'"', false);
});

it('conserva la busqueda por search_value y los campos ocultos del resto de filtros', function () {
    $admin = crearAdminTablaGoleadorMigration();
    $contexto = crearContextoTablaGoleadorMigration();
    crearGoleadorMigration($contexto, '40300050', 'Lucas', 'Cabrera', 6);

    $response = $this->actingAs($admin)->get(route('tabla_goleador.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'search_value' => 'cabrera',
    ]));

    $response->assertOk()
        ->assertSee('name="search_value"', false)
        ->assertSee('Lucas')
        ->assertSee('name="idCategoria" value="'.$contexto['categoria']->id.'"', false);
});
