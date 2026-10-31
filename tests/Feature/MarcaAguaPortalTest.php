<?php

use App\Models\Edicion;
use App\Models\User;

it('muestra una marca de agua con el logo en cada pantalla pública', function (string $ruta) {
    Edicion::create(['nombre' => 'Edición marca de agua']);

    $html = $this->get(route($ruta))->assertOk()->getContent();

    expect(substr_count($html, 'class="tm-marca-agua"'))->toBe(1)
        ->and($html)->toContain(asset('fotos/Logo_Mitai_SinFondo.png'));
})->with([
    'home', 'campeones', 'subcampeones', 'goleadores', 'vallas', 'fixture',
    'tabla-posiciones', 'tabla-goleadores', 'tabla-vallas', 'instancias-finales',
    'terminos', 'privacidad', 'email.baja-general.form',
]);

it('no muestra la marca de agua en acceso ni panel', function () {
    $this->get(route('login'))->assertOk()->assertDontSee('class="tm-marca-agua"', false);

    $admin = User::factory()->create(['rol' => 'admin', 'email_verified_at' => now()]);
    $this->actingAs($admin)->get(route('admin'))->assertOk()->assertDontSee('class="tm-marca-agua"', false);
});

it('define la marca centrada sin filtro y la oculta al imprimir', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('width: min(760px, 95vw);', 'transform: translate(-50%, -50%);', 'filter: none;', '@media print', '.tm-marca-agua { display: none', 'z-index: -1;');

    $baja = $this->get(route('email.baja-general.form'))->assertOk()->getContent();
    expect($baja)->toContain('relative z-0');
});
