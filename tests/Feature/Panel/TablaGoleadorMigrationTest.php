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

// Extrae el <form> del control de borrado real (x-ui.confirm-delete), y no
// cualquier otro punto de la página que también renderice el mismo nombre de
// campo (por ejemplo el form de filtros re-emite idEdicion como oculto).
function extraerFormularioEliminarGoleador(string $html, string $actionUrl): string
{
    preg_match('/<form\b[^>]*\baction="'.preg_quote($actionUrl, '/').'"[^>]*\bdata-confirm-delete\b.*?<\/form>/s', $html, $coincidencias);

    return $coincidencias[0] ?? '';
}

it('renderiza la correccion manual y el link al portal con la categoria activa', function () {
    $admin = crearAdminTablaGoleadorMigration();
    $contexto = crearContextoTablaGoleadorMigration();
    crearGoleadorMigration($contexto, '40300001', 'Mateo', 'Duarte', 5);

    $response = $this->actingAs($admin)->get(route('tabla_goleador.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]));

    $hrefCategoriaActiva = e(route('tabla_goleador.index', ['idEdicion' => $contexto['edicion']->id, 'idCategoria' => $contexto['categoria']->id]));

    $response->assertOk()
        ->assertSee('Corrección manual')
        ->assertSee(e(route('seleccionar-categoria', ['idEdicion' => $contexto['edicion']->id, 'tipo' => 'tabla_goleador'])), false)
        ->assertSee(e(route('tabla-goleadores', ['idEdicion' => $contexto['edicion']->id, 'idCategoria' => $contexto['categoria']->id])), false)
        ->assertSee('target="_blank"', false)
        ->assertSee('rel="noopener"', false)
        ->assertSee('Ver en el portal');

    // aria-current="page" atado al <a> del chip de la categoría activa, no a
    // cualquier otro elemento de la página que también lo emita.
    expect($response->getContent())->toMatch('/<a href="'.preg_quote($hrefCategoriaActiva, '/').'"\s+aria-current="page"/');
});

it('muestra el podio de los primeros tres goleadores de la categoria ordenado por goles, no por insercion', function () {
    $admin = crearAdminTablaGoleadorMigration();
    $contexto = crearContextoTablaGoleadorMigration();

    // Orden de alta deliberadamente distinto al de goles, para que la
    // aserción de orden solo pueda pasar si la vista realmente ordena por
    // cantidadGoles (revisión R3-podio-orden-no-probado): el controlador ya
    // ordena por goles desc (líneas 37-38), pero el test viejo insertaba en
    // ese mismo orden y no distinguía "ordenado por goles" de "ordenado por
    // alta/id".
    crearGoleadorMigration($contexto, '40300010', 'Cinco', 'Alvarez', 5);
    crearGoleadorMigration($contexto, '40300011', 'Doce', 'Bianchi', 12);
    crearGoleadorMigration($contexto, '40300012', 'Siete', 'Costa', 7);
    crearGoleadorMigration($contexto, '40300013', 'Nueve', 'Duarte', 9);

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
        ->assertSeeInOrder(['data-podio-posicion="1"', 'Doce', 'data-podio-posicion="2"', 'Nueve', 'data-podio-posicion="3"', 'Siete'], false)
        ->assertSeeInOrder(['data-posicion="4"', 'Cinco'], false);
});

it('no muestra podio cuando no hay categoria seleccionada y conserva la columna de categoria', function () {
    $admin = crearAdminTablaGoleadorMigration();
    $contexto = crearContextoTablaGoleadorMigration();
    crearGoleadorMigration($contexto, '40300020', 'Bruno', 'Sosa', 4);

    $response = $this->actingAs($admin)->get(route('tabla_goleador.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()->assertDontSee('data-podio-posicion', false);

    // "Categoría" también aparece en el aria-label del contenedor de chips y
    // el nombre de la categoría también aparece como texto del chip de
    // filtro: ninguna de esas dos coincidencias prueba que la columna de la
    // tabla (el <th> del encabezado y el <td> de cada fila) siga ahí
    // (revisión R3-columna-categoria-ambigua).
    expect($response->getContent())
        ->toMatch('/<th scope="col">Categoría<\/th>/')
        ->toMatch('/<td>'.preg_quote($contexto['categoria']->nombreCategoria, '/').'<\/td>/');
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

    $response->assertOk()->assertSee('data-confirm-delete', false);

    // El form de filtros re-emite idEdicion como oculto con el mismo
    // nombre/valor: la aserción tiene que mirar específicamente el <form>
    // del borrado, no cualquier idEdicion oculto de la página (revisión
    // R3-idEdicion-hidden-ambiguo-goleador).
    $formularioEliminar = extraerFormularioEliminarGoleador($response->getContent(), e(route('tabla_goleador.destroy', $goleador)));

    expect($formularioEliminar)->not->toBe('')
        ->and($formularioEliminar)->toContain('name="idEdicion" value="'.$contexto['edicion']->id.'"');
});

it('sin busqueda el ranking de la columna # es correcto y ordenado por goles', function () {
    $admin = crearAdminTablaGoleadorMigration();
    $contexto = crearContextoTablaGoleadorMigration();

    crearGoleadorMigration($contexto, '40300050', 'Lucas', 'Cabrera', 6);
    crearGoleadorMigration($contexto, '40300051', 'Martin', 'Diaz', 10);

    $response = $this->actingAs($admin)->get(route('tabla_goleador.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]));

    $response->assertOk()
        ->assertSeeInOrder(['data-posicion="1"', 'Martin', 'data-posicion="2"', 'Lucas'], false)
        ->assertDontSee('>—<', false);
});

it('con busqueda por search_value no muestra podio ni rangos enganosos, pero conserva los campos ocultos', function () {
    $admin = crearAdminTablaGoleadorMigration();
    $contexto = crearContextoTablaGoleadorMigration();
    crearGoleadorMigration($contexto, '40300060', 'Lucas', 'Cabrera', 6);
    crearGoleadorMigration($contexto, '40300061', 'Martin', 'Diaz', 10);

    $response = $this->actingAs($admin)->get(route('tabla_goleador.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'search_value' => 'cabrera',
    ]));

    $response->assertOk()
        ->assertSee('name="search_value"', false)
        ->assertSee('Lucas')
        ->assertDontSee('Martin')
        ->assertSee('name="idCategoria" value="'.$contexto['categoria']->id.'"', false)
        // Con una sola categoría en juego, una búsqueda que aísla un solo
        // resultado dejaría a ese resultado en la posición 1 del podio si
        // la condición ignorara search_value: no tiene que aparecer ningún
        // podio ni ningún data-posicion numérico, solo el guion neutral
        // (revisión R3-podio-busqueda).
        ->assertDontSee('data-podio-posicion', false)
        ->assertDontSee('data-posicion=', false)
        ->assertSee('>—<', false);
});
