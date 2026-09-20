<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Partido;
use App\Models\User;

// Helpers con prefijo propio: las funciones de Pest son globales entre archivos
// del mismo run y no pueden redeclararse (convencion de FixtureExploradorVistaTest).
function headAssetsContexto(): array
{
    $edicion = Edicion::create(['nombre' => 'Edicion Head Assets']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 14']);
    $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Grupo A']);
    $fecha = Fecha::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Fecha Head Assets',
    ]);

    return compact('edicion', 'categoria', 'grupo', 'fecha');
}

function headAssetsPartido(array $contexto): Partido
{
    $equipoLocal = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => 'Leones del Norte',
        'foto' => 'local.png',
    ]);
    $equipoVisitante = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => 'Tigres del Sur',
        'foto' => 'visita.png',
    ]);

    foreach ([$equipoLocal, $equipoVisitante] as $equipo) {
        EquipoEdicion::create([
            'idEdicion' => $contexto['edicion']->id,
            'idEquipo' => $equipo->id,
            'idCategoria' => $contexto['categoria']->id,
        ]);
    }

    return Partido::create([
        'idCategoria' => $contexto['categoria']->id,
        'idFechas' => $contexto['fecha']->id,
        'idEquipoLocal' => $equipoLocal->id,
        'idEquipoVisitante' => $equipoVisitante->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'golesEquipoLocal' => null,
        'golesEquipoVisitante' => null,
        'horario_datetime' => now()->addDay()->format('Y-m-d H:i:s'),
        'cancha' => 1,
        'jugado' => 0,
    ]);
}

function headAssetsAdmin(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

it('precarga el hero en home y omite SweetAlert2', function () {
    headAssetsContexto();

    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)->toMatch('/<link rel="preload"[^>]*fondo_en_cancha\.webp/')
        ->and($html)->not->toContain('sweetalert2@11');
});

it('precarga el hero en fixture con alta prioridad y decoding async sin SweetAlert2', function () {
    $contexto = headAssetsContexto();
    headAssetsPartido($contexto);

    $html = $this->get(route('fixture', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]))->assertOk()->getContent();

    preg_match('/<img[^>]*fondo_en_cancha\.webp[^>]*>/', $html, $coincidencias);
    $hero = $coincidencias[0] ?? '';

    expect($html)->toMatch('/<link rel="preload"[^>]*fondo_en_cancha\.webp/')
        ->and($html)->not->toContain('sweetalert2@11')
        ->and($hero)->toContain('fetchpriority="high"')
        ->and($hero)->toContain('decoding="async"');
});

it('omite la precarga del hero y registra el service worker en campeones', function () {
    $contexto = headAssetsContexto();

    $html = $this->get(route('campeones', ['idEdicion' => $contexto['edicion']->id]))->assertOk()->getContent();

    expect($html)->not->toMatch('/<link rel="preload"[^>]*fondo_en_cancha\.webp/')
        ->and($html)->toContain("serviceWorker.register('/sw.js'");
});

it('carga SweetAlert2 y admin JS en Panel admin sin welcome ni service worker', function () {
    $admin = headAssetsAdmin();
    $contexto = headAssetsContexto();
    headAssetsPartido($contexto);

    $html = $this->actingAs($admin)
        ->get(route('admin', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('sweetalert2@11');
    // Cubre dev server (public/hot) y build hasheado sin acoplarse al hash.
    expect($html)->toMatch('/(resources\/js\/admin\/admin\.js|build\/assets\/admin-[A-Za-z0-9_-]+\.js)/')
        ->and($html)->not->toMatch('/(resources\/js\/welcome\.js|build\/assets\/welcome-[A-Za-z0-9_-]+\.js)/')
        ->and($html)->not->toMatch('/<link rel="preload"[^>]*fondo_en_cancha\.webp/')
        ->and($html)->not->toContain("serviceWorker.register('/sw.js'");
});
