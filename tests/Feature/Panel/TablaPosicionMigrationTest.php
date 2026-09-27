<?php

// Feature tests for the Tabla de posiciones module migration onto the shared
// x-ui.* components (T8a, panel-admin-redesign). Covers the canvas layout:
// category chips, one card per grupo with a compact column set (#, Equipo,
// PJ, G, E, P, DG, Pts), a top-2 highlight badge, and the "Ver en el portal"
// link towards the public tabla-posiciones route. Existing filters, the
// create/delete parity and TablaPosicionCrudTest keep passing unchanged.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Grupo;
use App\Models\TablaPosicion;
use App\Models\User;

function crearAdminTablaPosicionMigration(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function crearContextoTablaPosicionMigration(array $atributos = []): array
{
    $edicion = Edicion::create(['nombre' => $atributos['nombreEdicion'] ?? 'Edicion Posiciones Migration']);
    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => $atributos['nombreCategoria'] ?? 'Sub 13 Migration',
    ]);
    $grupo = Grupo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => $atributos['nombreGrupo'] ?? 'Grupo A',
    ]);

    return compact('edicion', 'categoria', 'grupo');
}

function crearEquipoPosicionMigration(array $contexto, string $nombre): Equipo
{
    $equipo = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $nombre,
        'foto' => 'escudo.png',
    ]);

    EquipoEdicion::create([
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $equipo->id,
        'idCategoria' => $contexto['categoria']->id,
    ]);

    return $equipo;
}

function crearFilaPosicionMigration(array $contexto, Equipo $equipo, array $atributos = []): TablaPosicion
{
    return TablaPosicion::create(array_merge([
        'idGrupo' => $contexto['grupo']->id,
        'idEquipo' => $equipo->id,
        'idEdicion' => $contexto['edicion']->id,
        'puntos' => 10,
        'golesFavor' => 8,
        'golesContra' => 3,
        'diferenciaGoles' => 5,
        'jugado' => 4,
        'ganado' => 3,
        'perdido' => 0,
        'empatado' => 1,
    ], $atributos));
}

it('renderiza el encabezado, la categoria activa y el link al portal', function () {
    $admin = crearAdminTablaPosicionMigration();
    $contexto = crearContextoTablaPosicionMigration();
    $equipo = crearEquipoPosicionMigration($contexto, 'Mitai Azul');
    crearFilaPosicionMigration($contexto, $equipo);

    $response = $this->actingAs($admin)->get(route('tabla_posicion.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
    ]));

    $parametros = [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
    ];

    $response->assertOk()
        ->assertSee('aria-current="page"', false)
        ->assertSee(e(route('tabla_posicion.create', $parametros)), false)
        ->assertSee(e(route('tabla-posiciones', $parametros)), false)
        ->assertSee('target="_blank"', false)
        ->assertSee('rel="noopener"', false)
        ->assertSee('Ver en el portal');
});

it('muestra el ranking compacto por grupo con la insignia de clasificacion en el top dos', function () {
    $admin = crearAdminTablaPosicionMigration();
    $contexto = crearContextoTablaPosicionMigration();

    $equipoUno = crearEquipoPosicionMigration($contexto, 'Mitai Uno');
    $equipoDos = crearEquipoPosicionMigration($contexto, 'Mitai Dos');
    $equipoTres = crearEquipoPosicionMigration($contexto, 'Mitai Tres');

    crearFilaPosicionMigration($contexto, $equipoUno, ['puntos' => 15]);
    crearFilaPosicionMigration($contexto, $equipoDos, ['puntos' => 12]);
    crearFilaPosicionMigration($contexto, $equipoTres, ['puntos' => 3]);

    $response = $this->actingAs($admin)->get(route('tabla_posicion.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]));

    $response->assertOk()
        ->assertSee('>PJ<', false)
        ->assertSee('>Pts<', false)
        ->assertSee('data-posicion="1"', false)
        ->assertSee('data-posicion="2"', false)
        ->assertSee('data-posicion="3"', false)
        ->assertSee('data-destacado="clasifica"', false)
        ->assertSeeInOrder(['data-posicion="1"', 'Mitai Uno', 'data-posicion="2"', 'Mitai Dos', 'data-posicion="3"', 'Mitai Tres'], false);

    expect(substr_count($response->getContent(), 'data-destacado="clasifica"'))->toBe(2);
});

it('conserva los goles a favor, en contra y en contra generales de la edicion', function () {
    $admin = crearAdminTablaPosicionMigration();
    $contexto = crearContextoTablaPosicionMigration();
    $equipo = crearEquipoPosicionMigration($contexto, 'Mitai Goles');
    EquipoEdicion::query()
        ->where('idEdicion', $contexto['edicion']->id)
        ->where('idEquipo', $equipo->id)
        ->update(['golesContra' => 17]);
    crearFilaPosicionMigration($contexto, $equipo, ['golesFavor' => 23, 'golesContra' => 9, 'diferenciaGoles' => 14]);

    $response = $this->actingAs($admin)->get(route('tabla_posicion.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]));

    $response->assertOk()
        ->assertSeeInOrder(['>GF<', '>GC<', '>DG<', 'GC Gral'], false)
        ->assertSeeInOrder(['Mitai Goles', '>23<', '>9<', '>14<', '>17<'], false);
});

it('elimina con confirmacion real y conserva los campos ocultos de contexto', function () {
    $admin = crearAdminTablaPosicionMigration();
    $contexto = crearContextoTablaPosicionMigration();
    $equipo = crearEquipoPosicionMigration($contexto, 'Mitai Azul');
    $fila = crearFilaPosicionMigration($contexto, $equipo);

    $response = $this->actingAs($admin)->get(route('tabla_posicion.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]));

    $response->assertOk()
        ->assertSee('data-confirm-delete', false)
        ->assertSee(e(route('tabla_posicion.destroy', $fila)), false)
        ->assertSee('name="idEdicion" value="'.$contexto['edicion']->id.'"', false)
        ->assertSee('name="idGrupo" value="'.$contexto['grupo']->id.'"', false);
});

it('renderiza el estado vacio con el componente compartido cuando no hay posiciones', function () {
    $admin = crearAdminTablaPosicionMigration();
    $contexto = crearContextoTablaPosicionMigration();

    $response = $this->actingAs($admin)->get(route('tabla_posicion.index', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('No existen registros')
        ->assertSee('border-dashed', false);
});
