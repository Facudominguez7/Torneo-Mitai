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
