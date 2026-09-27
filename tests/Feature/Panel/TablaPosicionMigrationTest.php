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

// Extrae el <form> del control de borrado real (x-ui.confirm-delete), y no
// cualquier otro punto de la página que también renderice el mismo nombre de
// campo (por ejemplo el form de filtros re-emite idEdicion/idCategoria como
// ocultos con el mismo valor).
function extraerFormularioEliminarPosicion(string $html, string $actionUrl): string
{
    preg_match('/<form\b[^>]*\baction="'.preg_quote($actionUrl, '/').'"[^>]*\bdata-confirm-delete\b.*?<\/form>/s', $html, $coincidencias);

    return $coincidencias[0] ?? '';
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
        ->assertSee(e(route('tabla_posicion.create', $parametros)), false)
        ->assertSee(e(route('tabla-posiciones', $parametros)), false)
        ->assertSee('target="_blank"', false)
        ->assertSee('rel="noopener"', false)
        ->assertSee('Ver en el portal');

    // aria-current="page" atado al <a> del chip de la categoría activa, no a
    // cualquier otro elemento de la página que también lo emita.
    $hrefCategoriaActiva = e(route('tabla_posicion.index', $parametros));
    expect($response->getContent())->toMatch('/<a href="'.preg_quote($hrefCategoriaActiva, '/').'"\s+aria-current="page"/');
});

it('muestra el ranking compacto por grupo con la insignia de clasificacion en el top dos', function () {
    $admin = crearAdminTablaPosicionMigration();
    $contexto = crearContextoTablaPosicionMigration();

    $equipoUno = crearEquipoPosicionMigration($contexto, 'Mitai Uno');
    $equipoDos = crearEquipoPosicionMigration($contexto, 'Mitai Dos');
    $equipoTres = crearEquipoPosicionMigration($contexto, 'Mitai Tres');

    // Puntos asignados deliberadamente fuera del orden de alta (Uno, Dos,
    // Tres) para que la aserción de orden solo pueda pasar si el ranking
    // realmente se ordena por puntos y no por id/alta (revisión
    // R3-ranking-posicion-orden): el orden esperado es Tres (15), Uno (12),
    // Dos (3).
    crearFilaPosicionMigration($contexto, $equipoUno, ['puntos' => 12]);
    crearFilaPosicionMigration($contexto, $equipoDos, ['puntos' => 3]);
    crearFilaPosicionMigration($contexto, $equipoTres, ['puntos' => 15]);

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
        ->assertSeeInOrder(['data-posicion="1"', 'Mitai Tres', 'data-posicion="2"', 'Mitai Uno', 'data-posicion="3"', 'Mitai Dos'], false);

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

    $response->assertOk()->assertSee('data-confirm-delete', false);

    // El form de filtros re-emite idEdicion (y en su create/edit,
    // idCategoria) como oculto con el mismo valor: la aserción tiene que
    // mirar específicamente el <form> del borrado, no cualquier campo
    // oculto homónimo de la página (revisión
    // R3-idEdicion-hidden-ambiguo-posicion).
    $formularioEliminar = extraerFormularioEliminarPosicion($response->getContent(), e(route('tabla_posicion.destroy', $fila)));

    expect($formularioEliminar)->not->toBe('')
        ->and($formularioEliminar)->toContain('name="idEdicion" value="'.$contexto['edicion']->id.'"')
        ->and($formularioEliminar)->toContain('name="idCategoria" value="'.$contexto['categoria']->id.'"')
        ->and($formularioEliminar)->toContain('name="idGrupo" value="'.$contexto['grupo']->id.'"');
});

it('renderiza el estado vacio con el componente compartido cuando no hay posiciones', function () {
    $admin = crearAdminTablaPosicionMigration();
    $contexto = crearContextoTablaPosicionMigration();

    $response = $this->actingAs($admin)->get(route('tabla_posicion.index', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('No existen registros')
        ->assertSee('border-dashed', false);
});
