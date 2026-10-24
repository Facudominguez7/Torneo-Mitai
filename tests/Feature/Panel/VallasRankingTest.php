<?php

// Feature tests del ranking de Vallas menos vencidas (T8c, panel-admin-redesign):
// orden por goles en contra, columna de arquero, chips de categoría, link al
// portal, estados vacíos y acceso.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\User;
use App\Models\VallaMenosVencida;

function crearAdminVallasRanking(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

/**
 * @return array{edicion: Edicion, categoria: Categoria}
 */
function crearContextoVallasRanking(string $nombreCategoria = 'Sub 13 Vallas Ranking'): array
{
    $edicion = Edicion::create(['nombre' => 'Edicion Vallas Ranking']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => $nombreCategoria]);

    return compact('edicion', 'categoria');
}

function crearEquipoVallasRanking(Edicion $edicion, Categoria $categoria, string $nombre, int $golesContra): Equipo
{
    $equipo = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => $nombre,
        'foto' => 'escudo.png',
    ]);

    EquipoEdicion::create([
        'idEdicion' => $edicion->id,
        'idEquipo' => $equipo->id,
        'idCategoria' => $categoria->id,
        'golesContra' => $golesContra,
    ]);

    return $equipo;
}

it('un invitado no puede ver el ranking de vallas', function () {
    $this->get(route('vallas.index'))->assertRedirect('/login');
});

it('un usuario sin rol admin no puede ver el ranking de vallas', function () {
    $usuario = User::factory()->create(['rol' => 'regular', 'email_verified_at' => now()]);

    $this->actingAs($usuario)->get(route('vallas.index'))->assertRedirect('/');
});

it('ordena los equipos por goles en contra y a igual goles por nombre', function () {
    $admin = crearAdminVallasRanking();
    $contexto = crearContextoVallasRanking();

    // Insertados fuera de orden para que el test pruebe el ordenamiento.
    crearEquipoVallasRanking($contexto['edicion'], $contexto['categoria'], 'Zorzales', 9);
    crearEquipoVallasRanking($contexto['edicion'], $contexto['categoria'], 'Benteveos', 3);
    crearEquipoVallasRanking($contexto['edicion'], $contexto['categoria'], 'Albatros', 9);
    crearEquipoVallasRanking($contexto['edicion'], $contexto['categoria'], 'Calandrias', 5);

    $response = $this->actingAs($admin)->get(route('vallas.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]));

    $response->assertOk()->assertSeeInOrder([
        'data-posicion="1"', 'Benteveos', '>3<',
        'data-posicion="2"', 'Calandrias', '>5<',
        'data-posicion="3"', 'Albatros', '>9<',
        'data-posicion="4"', 'Zorzales', '>9<',
    ], false);
});

it('muestra el arquero guardado o Sin definir', function () {
    $admin = crearAdminVallasRanking();
    $contexto = crearContextoVallasRanking();
    $conArquero = crearEquipoVallasRanking($contexto['edicion'], $contexto['categoria'], 'Con Arquero', 2);
    crearEquipoVallasRanking($contexto['edicion'], $contexto['categoria'], 'Sin Arquero', 4);
    VallaMenosVencida::create([
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idEquipo' => $conArquero->id,
        'nombre' => 'Tomás Benítez',
    ]);

    $html = $this->actingAs($admin)->get(route('vallas.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]))->assertOk()->getContent();

    expect($html)
        ->toMatch('/<tr data-posicion="1">.*?Con Arquero.*?Tomás Benítez.*?<\/tr>/s')
        ->toMatch('/<tr data-posicion="2">.*?Sin Arquero.*?Sin definir.*?<\/tr>/s');
});

it('muestra la columna de categoria solo sin categoria elegida', function () {
    $admin = crearAdminVallasRanking();
    $contexto = crearContextoVallasRanking('Categoria Ranking Unica');
    crearEquipoVallasRanking($contexto['edicion'], $contexto['categoria'], 'Equipo Columna', 1);

    $todas = $this->actingAs($admin)->get(route('vallas.index', ['idEdicion' => $contexto['edicion']->id]))->getContent();
    $filtrada = $this->actingAs($admin)->get(route('vallas.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]))->getContent();

    expect($todas)
        ->toMatch('/<th scope="col">Categoría<\/th>/')
        ->toMatch('/<td>\s*Categoria Ranking Unica\s*<\/td>/')
        ->and($filtrada)->not->toMatch('/<th scope="col">Categoría<\/th>/');
});

it('marca el chip de la categoria activa y enlaza al portal con el filtro', function () {
    $admin = crearAdminVallasRanking();
    $contexto = crearContextoVallasRanking();
    crearEquipoVallasRanking($contexto['edicion'], $contexto['categoria'], 'Equipo Chip', 1);
    $parametros = ['idEdicion' => $contexto['edicion']->id, 'idCategoria' => $contexto['categoria']->id];

    $html = $this->actingAs($admin)->get(route('vallas.index', $parametros))->assertOk()->getContent();

    $hrefChip = preg_quote(e(route('vallas.index', $parametros)), '/');
    $hrefPortal = preg_quote(e(route('tabla-vallas', $parametros)), '/');

    // Solo dentro de los chips: el ítem activo del menú también lleva aria-current.
    preg_match('/<div[^>]*aria-label="Categoría"[^>]*>(.*?)<\/div>/s', $html, $chips);
    $hrefTodas = preg_quote(e(route('vallas.index', ['idEdicion' => $contexto['edicion']->id])), '/');

    expect($chips[1] ?? '')
        ->toMatch('/<a[^>]*href="'.$hrefChip.'"[^>]*aria-current="page"/s')
        ->not->toMatch('/<a[^>]*href="'.$hrefTodas.'"[^>]*aria-current="page"/s')
        ->and($html)->toMatch('/<a[^>]*href="'.$hrefPortal.'"[^>]*target="_blank"[^>]*rel="noopener"/s');
});

it('renderiza el estado vacio sin equipos y sin edicion en contexto', function () {
    $admin = crearAdminVallasRanking();
    $contexto = crearContextoVallasRanking();

    $this->actingAs($admin)->get(route('vallas.index', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->assertSee('Todavía no hay equipos cargados en esta edición');

    Edicion::query()->delete();

    $this->actingAs($admin)->get(route('vallas.index'))
        ->assertOk()
        ->assertSee('Seleccioná una edición para ver el ranking de vallas');
});

it('ignora un idCategoria mal formado en vez de fallar', function () {
    $admin = crearAdminVallasRanking();
    $contexto = crearContextoVallasRanking();
    crearEquipoVallasRanking($contexto['edicion'], $contexto['categoria'], 'Equipo Malformado', 2);

    foreach ([['idCategoria' => ['1', '2']], ['idCategoria' => 'abc']] as $parametros) {
        $this->actingAs($admin)
            ->get(route('vallas.index', ['idEdicion' => $contexto['edicion']->id] + $parametros))
            ->assertOk()
            ->assertSee('Equipo Malformado');
    }
});
