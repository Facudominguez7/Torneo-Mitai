<?php

use App\Models\Edicion;
use App\Models\User;

it('condensa la navegacion publica en una capsula flotante con histeresis', function (string $ruta) {
    Edicion::create(['nombre' => 'Edición navegación']);
    $html = $this->get(route($ruta))->assertOk()->getContent();
    $css = file_get_contents(resource_path('css/app.css'));

    // Condenses past 40px and only expands again near the top (8px), so the bar does not flicker.
    expect($html)->toContain('nav-capsula', "classList.toggle('nav-condensada'", 'window.scrollY > 40', 'window.scrollY < 8')
        ->not->toContain('nav-compactable', '.inert =')
        ->and($css)->toContain('.nav-condensada .nav-capsula', 'cubic-bezier(0.16, 1, 0.3, 1)', 'prefers-reduced-motion: no-preference');
})->with(['home', 'fixture', 'campeones']);

it('mantiene la navegacion anterior en perfil autenticado', function () {
    $usuario = User::factory()->create();
    $html = html_entity_decode($this->actingAs($usuario)->get(route('profile.edit'))->assertOk()->getContent());

    expect($html)->toContain('[.nav-compacta_&]:hidden', '[.nav-compacta_&]:py-2', 'new IntersectionObserver')
        ->not->toContain('nav-capsula', 'window.scrollY');
});

it('no compacta la navegacion del perfil si la pagina no tiene recorrido para absorberlo', function () {
    $usuario = User::factory()->create();
    $html = html_entity_decode($this->actingAs($usuario)->get(route('profile.edit'))->assertOk()->getContent());

    expect($html)->toContain('document.documentElement.scrollHeight - window.innerHeight', 'barra.offsetHeight')
        ->toContain("document.documentElement.style.overflowAnchor = 'none'");
});

it('aplica la capsula publica tambien a usuarios autenticados', function () {
    Edicion::create(['nombre' => 'Edición navegación']);
    $usuario = User::factory()->create();
    $html = $this->actingAs($usuario)->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('nav-capsula', 'window.scrollY > 40')
        ->not->toContain('nav-compactable');
});

it('ubica las barras fijas de cada pagina debajo de la capsula flotante y no del nav', function (string $ruta) {
    Edicion::create(['nombre' => 'Edición navegación']);
    $html = $this->get(route($ruta))->assertOk()->getContent();

    // The capsule is lowered with transform, which the <nav> box does not reflect: measuring the nav hides the bar under it.
    expect($html)->toContain("document.querySelector('#nav-principal .nav-capsula')")
        ->not->toContain("const navegacion = document.getElementById('nav-principal');");
})->with(['fixture', 'tabla-goleadores', 'instancias-finales']);
