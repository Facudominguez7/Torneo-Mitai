<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\TablaGoleador;

function crearContextoTablaGoleadores(): array
{
    $edicion = Edicion::create([
        'nombre' => 'Edición Test Goleadores',
    ]);

    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 14',
    ]);

    $equipo = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Mitai Naranja',
        'foto' => 'escudo.png',
    ]);

    return compact('edicion', 'categoria', 'equipo');
}

function crearRegistroGoleador(array $contexto, int $dni, string $nombre, string $apellido, int $goles): void
{
    Jugador::create([
        'dni' => $dni,
        'nombre' => $nombre,
        'apellido' => $apellido,
        'goles_totales' => $goles,
        'partidos_totales' => 1,
    ]);

    TablaGoleador::create([
        'idEquipo' => $contexto['equipo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'dni_jugador' => $dni,
        'cantidadGoles' => $goles,
    ]);
}

it('aplica busqueda case insensitive por nombre de jugador', function () {
    $contexto = crearContextoTablaGoleadores();

    crearRegistroGoleador($contexto, 100001, 'LuCaS', 'PereZ', 8);
    crearRegistroGoleador($contexto, 100002, 'Martin', 'Gomez', 6);

    $response = $this->get(route('tabla-goleadores', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'search_value' => 'lucas',
    ]));

    $response->assertOk()
        ->assertSee('PereZ LuCaS')
        ->assertDontSee('Gomez Martin');
});

it('encuentra un jugador fuera de la primera pagina al filtrar antes de paginar', function () {
    $contexto = crearContextoTablaGoleadores();

    for ($i = 1; $i <= 30; $i++) {
        crearRegistroGoleador($contexto, 200000 + $i, 'Jugador'.$i, 'Comun', 200 - $i);
    }

    crearRegistroGoleador($contexto, 299999, 'Ulises', 'Objetivo', 1);

    $listadoSinFiltro = $this->get(route('tabla-goleadores', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]));

    $listadoSinFiltro->assertOk()
        ->assertDontSee('Objetivo Ulises');

    $listadoFiltrado = $this->get(route('tabla-goleadores', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'search_value' => 'ulises',
    ]));

    $listadoFiltrado->assertOk()
        ->assertSee('Objetivo Ulises')
        ->assertSee('search_value=ulises', false);
});
