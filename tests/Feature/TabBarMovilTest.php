<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\User;

function tabBarBase(): array
{
    $edicion = Edicion::create(['nombre' => 'Edicion Tab Bar']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 14']);
    $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Grupo A']);
    $fecha = Fecha::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Fecha Tab Bar',
    ]);

    return compact('edicion', 'categoria', 'grupo', 'fecha');
}

function tabBarMovil(string $html): string
{
    preg_match_all('/<nav class="tabbar-movil.*?<\/nav>/s', $html, $coincidencias);

    return implode("\n", $coincidencias[0] ?? []);
}

function pestanaTabBar(string $barra, string $etiqueta): ?string
{
    preg_match_all('/<a\b[^>]*>.*?<\/a>/s', $barra, $coincidencias, PREG_SET_ORDER);

    foreach ($coincidencias as $coincidencia) {
        if (str_contains(trim(strip_tags($coincidencia[0])), $etiqueta)) {
            return $coincidencia[0];
        }
    }

    return null;
}

it('renderiza las cinco pestanas en el home con aria-current en Inicio', function () {
    $contexto = tabBarBase();

    $html = $this->get(route('home', ['idEdicion' => $contexto['edicion']->id]))->assertOk()->getContent();
    $barra = tabBarMovil($html);

    expect($barra)->not->toBeEmpty();

    foreach (['Inicio', 'Partidos', 'Posiciones', 'Goleadores', 'Finales'] as $etiqueta) {
        expect(pestanaTabBar($barra, $etiqueta), "Falta la pestaña $etiqueta")->not->toBeNull();
    }

    expect(pestanaTabBar($barra, 'Inicio'))->toContain('aria-current="page"')
        ->and(pestanaTabBar($barra, 'Partidos'))->toContain('idEdicion='.$contexto['edicion']->id)
        ->not->toContain('aria-current="page"');
});

it('marca Partidos como activo en la pagina de fixture', function () {
    $contexto = tabBarBase();

    $html = $this->get(route('fixture', ['idEdicion' => $contexto['edicion']->id]))->assertOk()->getContent();
    $barra = tabBarMovil($html);

    expect($barra)->not->toBeEmpty()
        ->and(pestanaTabBar($barra, 'Partidos'))->toContain('aria-current="page"')
        ->and(pestanaTabBar($barra, 'Inicio'))->not->toContain('aria-current="page"');
});

it('adopta la barra en campeones y vallas', function () {
    $contexto = tabBarBase();
    $idEdicion = ['idEdicion' => $contexto['edicion']->id];

    expect(tabBarMovil($this->get(route('campeones', $idEdicion))->assertOk()->getContent()))->not->toBeEmpty()
        ->and(tabBarMovil($this->get(route('vallas', $idEdicion))->assertOk()->getContent()))->not->toBeEmpty();
});

it('enlaza Goleadores a la tabla conservando la edición', function () {
    $contexto = tabBarBase();
    $html = $this->get(route('fixture', ['idEdicion' => $contexto['edicion']->id]))->assertOk()->getContent();
    $enlace = html_entity_decode(pestanaTabBar(tabBarMovil($html), 'Goleadores'), ENT_QUOTES);

    expect($enlace)->toContain(route('tabla-goleadores', ['idEdicion' => $contexto['edicion']->id]));
});

it('marca Goleadores como activo en la tabla de goleadores', function () {
    $contexto = tabBarBase();

    $html = $this->get(route('tabla-goleadores', ['idEdicion' => $contexto['edicion']->id]))->assertOk()->getContent();
    $barra = tabBarMovil($html);

    expect($barra)->not->toBeEmpty()
        ->and(pestanaTabBar($barra, 'Goleadores'))->toContain('aria-current="page"');
});

it('no renderiza la barra movil en login ni en el panel admin', function () {
    $contexto = tabBarBase();

    $login = $this->get(route('login'))->assertOk()->getContent();
    $admin = User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
    $panel = $this->actingAs($admin)
        ->get(route('admin', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->getContent();

    expect($login)->not->toContain('tabbar-movil')
        ->and($panel)->not->toContain('tabbar-movil');
});
