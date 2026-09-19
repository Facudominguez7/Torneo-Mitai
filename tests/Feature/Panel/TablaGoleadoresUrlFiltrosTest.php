<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\TablaGoleador;

function crearContextoUrlFiltros(): array
{
    $edicion = Edicion::create([
        'nombre' => 'Edición Test URL Filtros',
    ]);

    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 16',
    ]);

    $otraCategoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 18',
    ]);

    $equipo = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Mitai Blanco',
        'foto' => 'escudo.png',
    ]);

    $equipoOtraCategoria = Equipo::create([
        'idCategoria' => $otraCategoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Mitai Negro',
        'foto' => 'escudo.png',
    ]);

    return compact('edicion', 'categoria', 'otraCategoria', 'equipo', 'equipoOtraCategoria');
}

function crearGoleadorUrlFiltros(array $contexto, int $dni, string $nombre, string $apellido, int $goles, ?int $idCategoria = null): void
{
    Jugador::create([
        'dni' => $dni,
        'nombre' => $nombre,
        'apellido' => $apellido,
        'goles_totales' => $goles,
        'partidos_totales' => 1,
    ]);

    TablaGoleador::create([
        'idEquipo' => $idCategoria === $contexto['otraCategoria']->id
            ? $contexto['equipoOtraCategoria']->id
            : $contexto['equipo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $idCategoria ?? $contexto['categoria']->id,
        'dni_jugador' => $dni,
        'cantidadGoles' => $goles,
    ]);
}

it('responde 200 con query string directo sin sesion previa y muestra el goleador', function () {
    $contexto = crearContextoUrlFiltros();

    crearGoleadorUrlFiltros($contexto, 300001, 'Tomas', 'Ibarra', 12);

    $response = $this->get(route('tabla-goleadores', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('Ibarra Tomas')
        ->assertSee($contexto['edicion']->nombre);
});

it('los links de orden preservan el estado de la url', function () {
    $contexto = crearContextoUrlFiltros();

    crearGoleadorUrlFiltros($contexto, 300002, 'Bruno', 'Diaz', 9);

    $response = $this->get(route('tabla-goleadores', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('idEdicion='.$contexto['edicion']->id, false);

    $conBusqueda = $this->get(route('tabla-goleadores', [
        'idEdicion' => $contexto['edicion']->id,
        'search_value' => 'diaz',
    ]));

    $conBusqueda->assertOk()
        ->assertSee('search_value=diaz', false);
});

it('el request ajax con filtros en la query devuelve el parcial sin html completo', function () {
    $contexto = crearContextoUrlFiltros();

    crearGoleadorUrlFiltros($contexto, 300003, 'Nico', 'Sosa', 7);

    $response = $this->get(route('tabla-goleadores', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]), ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertOk()
        ->assertSee('Sosa Nico')
        ->assertDontSee('<html', false);
});

it('el filtro idCategoria de la url excluye goleadores de otra categoria', function () {
    $contexto = crearContextoUrlFiltros();

    crearGoleadorUrlFiltros($contexto, 300004, 'Alan', 'Rojas', 5);
    crearGoleadorUrlFiltros($contexto, 300005, 'Pedro', 'Vuoso', 4, $contexto['otraCategoria']->id);

    $response = $this->get(route('tabla-goleadores', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]));

    $response->assertOk()
        ->assertSee('Rojas Alan')
        ->assertDontSee('Vuoso Pedro');
});

it('la url gana sobre filtros viejos guardados en sesion', function () {
    $contexto = crearContextoUrlFiltros();

    crearGoleadorUrlFiltros($contexto, 300006, 'Lucia', 'Benitez', 6);
    crearGoleadorUrlFiltros($contexto, 300007, 'Mora', 'Cabrera', 3, $contexto['otraCategoria']->id);

    // Sesión contaminada con la clave del patrón PRG que este change eliminó:
    // apunta a otra categoría y a una página inexistente.
    $response = $this->withSession(['tabla_goleadores_filtros' => [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['otraCategoria']->id,
        'search_value' => 'cabrera',
        'page' => 99,
    ]])->get(route('tabla-goleadores', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]));

    $response->assertOk()
        ->assertSee('Benitez Lucia')
        ->assertDontSee('Cabrera Mora');
});

it('el estado vacio por busqueda menciona el termino escapado y ofrece limpiar sin perder filtros', function () {
    $contexto = crearContextoUrlFiltros();

    $response = $this->get(route('tabla-goleadores', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'search_value' => '<b>Zurdo</b>',
    ]), ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertOk()
        ->assertSee('data-estado-vacio="busqueda"', false)
        ->assertSee('&lt;b&gt;Zurdo&lt;/b&gt;', false)
        ->assertDontSee('<b>Zurdo</b>', false)
        ->assertSee('limpiar-busqueda-goleadores', false)
        ->assertSee('Limpiar búsqueda');

    $contenido = $response->getContent();

    expect($contenido)->toContain('idCategoria='.$contexto['categoria']->id)
        ->and($contenido)->toContain('sort=goles')
        ->and($contenido)->toContain('dir=desc')
        ->and($contenido)->not->toContain('search_value=');
});

it('el estado vacio sin busqueda mantiene el mensaje generico sin accion de limpiar', function () {
    $contexto = crearContextoUrlFiltros();

    $response = $this->get(route('tabla-goleadores', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]), ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertOk()
        ->assertSee('data-estado-vacio="filtros"', false)
        ->assertSee('No existen registros para la combinación seleccionada.')
        ->assertDontSee('data-estado-vacio="busqueda"', false)
        ->assertDontSee('Limpiar búsqueda');
});

it('el ranking canonico en pagina 1 destaca el top tres y compacta desde el cuarto', function () {
    $contexto = crearContextoUrlFiltros();

    crearGoleadorUrlFiltros($contexto, 300021, 'Uno', 'Alvarez', 12);
    crearGoleadorUrlFiltros($contexto, 300022, 'Dos', 'Bianchi', 9);
    crearGoleadorUrlFiltros($contexto, 300023, 'Tres', 'Costa', 7);
    crearGoleadorUrlFiltros($contexto, 300024, 'Cuatro', 'Duarte', 5);
    crearGoleadorUrlFiltros($contexto, 300025, 'Cinco', 'Echenique', 3);

    $response = $this->get(route('tabla-goleadores', [
        'idEdicion' => $contexto['edicion']->id,
    ]), ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertOk()
        ->assertSee('data-zona="podio"', false)
        ->assertSee('data-variante="podio" data-posicion="1"', false)
        ->assertSee('data-variante="podio" data-posicion="2"', false)
        ->assertSee('data-variante="podio" data-posicion="3"', false)
        ->assertSee('data-destacado="principal"', false)
        ->assertDontSee('data-variante="podio" data-posicion="4"', false)
        ->assertSee('data-variante="compacta" data-posicion="4"', false)
        ->assertSee('data-variante="compacta" data-posicion="5"', false)
        ->assertSeeInOrder(['data-variante="podio" data-posicion="1"', 'Alvarez Uno'], false)
        ->assertSeeInOrder(['data-variante="compacta" data-posicion="4"', 'Duarte Cuatro', 'Mitai Blanco'], false);

    expect(substr_count($response->getContent(), 'data-destacado="secundario"'))->toBe(2);
});

it('un orden alternativo muestra todas las filas compactas sin podio', function () {
    $contexto = crearContextoUrlFiltros();

    crearGoleadorUrlFiltros($contexto, 300031, 'Uno', 'Alvarez', 12);
    crearGoleadorUrlFiltros($contexto, 300032, 'Dos', 'Bianchi', 9);
    crearGoleadorUrlFiltros($contexto, 300033, 'Tres', 'Costa', 7);
    crearGoleadorUrlFiltros($contexto, 300034, 'Cuatro', 'Duarte', 5);

    $response = $this->get(route('tabla-goleadores', [
        'idEdicion' => $contexto['edicion']->id,
        'sort' => 'nombre',
        'dir' => 'asc',
    ]), ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertOk()
        ->assertDontSee('data-zona="podio"', false)
        ->assertDontSee('data-variante="podio"', false)
        ->assertSee('data-variante="compacta" data-posicion="1"', false)
        ->assertSee('data-variante="compacta" data-posicion="4"', false);
});

it('la pagina dos del ranking canonico no muestra podio y conserva el offset global', function () {
    $contexto = crearContextoUrlFiltros();

    for ($i = 1; $i <= 31; $i++) {
        crearGoleadorUrlFiltros($contexto, 310000 + $i, 'Jugador'.$i, 'Goleador', 40 - $i);
    }

    $response = $this->get(route('tabla-goleadores', [
        'idEdicion' => $contexto['edicion']->id,
        'sort' => 'goles',
        'dir' => 'desc',
        'page' => 2,
    ]), ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertOk()
        ->assertDontSee('data-zona="podio"', false)
        ->assertDontSee('data-variante="podio"', false)
        ->assertSee('data-variante="compacta" data-posicion="31"', false);
});

it('la pagina completa publica los estados de carga error y delegacion de limpiar', function () {
    $contexto = crearContextoUrlFiltros();

    crearGoleadorUrlFiltros($contexto, 300041, 'Sole', 'Fontan', 2);

    $response = $this->get(route('tabla-goleadores', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('aria-busy="false"', false)
        ->assertSee('role="status"', false)
        ->assertSee('aria-live="polite"', false)
        ->assertSee('aria-atomic="true"', false)
        ->assertSee('id="estado-carga-anuncio"', false)
        ->assertSee('fixed inset-x-4 top-4 z-50', false)
        ->assertSee('md:static', false)
        ->assertSee('Reintentar', false)
        ->assertSee('a.limpiar-busqueda-goleadores', false);
});
