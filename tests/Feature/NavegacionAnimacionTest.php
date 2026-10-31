<?php

use App\Models\Edicion;
use App\Models\User;

it('expone una navegacion compactable con transiciones y controles inertes', function (string $ruta) {
    Edicion::create(['nombre' => 'Edición navegación']);
    $html = $this->get(route($ruta))->assertOk()->getContent();
    $css = file_get_contents(resource_path('css/app.css'));

    expect($html)->toContain('nav-compactable', "classList.toggle('nav-compacta'", '.inert =', 'window.scrollY')
        ->not->toContain('[.nav-compacta_&]:hidden')
        ->and($css)->toContain('.nav-compacta .nav-compactable', 'transition: max-width', 'prefers-reduced-motion: reduce');
})->with(['home', 'fixture', 'campeones']);

it('mantiene la navegacion anterior en perfil autenticado', function () {
    $usuario = User::factory()->create();
    $html = html_entity_decode($this->actingAs($usuario)->get(route('profile.edit'))->assertOk()->getContent());

    expect($html)->toContain('[.nav-compacta_&]:hidden', '[.nav-compacta_&]:py-2', 'new IntersectionObserver')
        ->not->toContain('nav-compactable', 'window.scrollY');
});

it('aplica la compactacion publica tambien a usuarios autenticados y conserva el observador movil', function () {
    Edicion::create(['nombre' => 'Edición navegación']);
    $usuario = User::factory()->create();
    $html = $this->actingAs($usuario)->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('nav-compactable', 'window.scrollY', 'new IntersectionObserver', 'matchMedia', '.inert =');
});
