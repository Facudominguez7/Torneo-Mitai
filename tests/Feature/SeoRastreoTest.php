<?php

// robots.txt, sitemap.xml y noindex para que Search Console rastree solo lo público.

use App\Models\Edicion;
use App\Models\User;

it('publica un robots.txt que bloquea el panel y declara el sitemap', function (): void {
    $respuesta = $this->get('/robots.txt')->assertOk();

    expect($respuesta->headers->get('Content-Type'))->toStartWith('text/plain')
        ->and($respuesta->getContent())->toBe("User-agent: *\nDisallow: /Panel\n\nSitemap: ".url('/sitemap.xml')."\n");
});

it('lista en el sitemap las paginas publicas y cada edicion', function (): void {
    $anterior = Edicion::create(['nombre' => '20° Edición Torneos Mita\'í']);
    $actual = Edicion::create(['nombre' => '21° Edición Torneos Mita\'í']);

    $respuesta = $this->get('/sitemap.xml')->assertOk();

    expect($respuesta->headers->get('Content-Type'))->toStartWith('application/xml');
    $respuesta
        ->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false)
        ->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false)
        ->assertSee('<loc>'.url('/').'</loc>', false)
        ->assertSee('<loc>'.url('/fixture').'</loc>', false)
        ->assertSee('<loc>'.url('/terminos-y-condiciones').'</loc>', false)
        ->assertSee('<loc>'.url('/politica-de-privacidad').'</loc>', false)
        ->assertSee('<loc>'.url('/campeones').'?idEdicion='.$anterior->id.'</loc>', false)
        ->assertSee('<loc>'.url('/tabla-de-posiciones').'?idEdicion='.$actual->id.'</loc>', false)
        ->assertDontSee('/login', false)
        ->assertDontSee('/Panel', false);
});

it('pide no indexar el acceso y las paginas privadas', function (string $ruta): void {
    $this->get($ruta)->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
})->with(['/login', '/register', '/forgot-password']);

it('deja indexar las paginas publicas', function (): void {
    Edicion::create(['nombre' => '21° Edición Torneos Mita\'í']);

    $this->get('/')->assertOk()
        ->assertSee('<meta name="robots" content="index, follow">', false);
});

it('pide no indexar el panel de administracion', function (): void {
    $edicion = Edicion::create(['nombre' => '21° Edición Torneos Mita\'í']);
    $admin = User::factory()->create(['rol' => 'admin', 'email_verified_at' => now()]);

    $this->actingAs($admin)->get(route('admin', ['idEdicion' => $edicion->id]))->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});
