<?php

// La home describe al torneo con datos estructurados (schema.org) para el panel de Google.

use App\Models\Edicion;

function seoJsonLd(string $html): ?array
{
    if (! preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $coincidencia)) {
        return null;
    }

    return json_decode($coincidencia[1], true, flags: JSON_THROW_ON_ERROR);
}

it('describe en la home la organizacion del torneo y el sitio', function (): void {
    Edicion::create(['nombre' => '21° Edición Torneos Mita\'í']);

    $datos = seoJsonLd($this->get('/')->assertOk()->getContent());

    expect($datos['@context'])->toBe('https://schema.org')
        ->and($datos['@graph'][0])->toBe([
            '@type' => 'SportsOrganization',
            '@id' => url('/').'#organizacion',
            'name' => "Torneos Mita'í",
            'url' => url('/'),
            'logo' => asset('fotos/Logo_Mitai_SinFondo.png'),
            'sport' => 'Fútbol',
            'description' => 'Torneo de fútbol infantil en Posadas, Misiones.',
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Posadas',
                'addressRegion' => 'Misiones',
                'addressCountry' => 'AR',
            ],
            'sameAs' => ['https://www.instagram.com/torneos.mitaii/', 'https://www.facebook.com/ligamitaijb'],
        ])
        ->and($datos['@graph'][1])->toBe([
            '@type' => 'WebSite',
            'name' => "Torneos Mita'í",
            'url' => url('/'),
            'inLanguage' => 'es-AR',
            'publisher' => ['@id' => url('/').'#organizacion'],
        ]);
});

it('no repite los datos estructurados fuera de la home', function (): void {
    $edicion = Edicion::create(['nombre' => '21° Edición Torneos Mita\'í']);

    expect(seoJsonLd($this->get('/fixture?idEdicion='.$edicion->id)->assertOk()->getContent()))->toBeNull();
});
