<?php

it('expone el sitemap con todas las paginas publicas', function () {
    $respuesta = $this->get('/sitemap.xml');

    $respuesta->assertOk();
    $respuesta->assertHeader('Content-Type', 'application/xml');

    $xml = $respuesta->getContent();

    expect($xml)->toContain('<?xml version="1.0" encoding="UTF-8"?>')
        ->and($xml)->toContain('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');

    foreach ([
        '/',
        '/fixture',
        '/campeones',
        '/subcampeones',
        '/goleadores',
        '/vallas',
        '/tabla-de-posiciones',
        '/tabla-de-goleadores',
        '/tabla-de-vallas',
        '/instancias-finales',
    ] as $ruta) {
        expect($xml)->toContain('<loc>'.url($ruta).'</loc>');
    }
});

it('no lista paginas privadas ni utilitarias en el sitemap', function () {
    $xml = $this->get('/sitemap.xml')->getContent();

    expect($xml)->not->toContain('/Panel')
        ->and($xml)->not->toContain('/login')
        ->and($xml)->not->toContain('/email/baja')
        ->and($xml)->not->toContain('/auth/');
});

it('declara el sitemap en robots.txt', function () {
    expect(file_get_contents(public_path('robots.txt')))->toContain('Sitemap: ');
});
