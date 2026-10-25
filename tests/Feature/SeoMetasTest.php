<?php

// Cada página pública tiene título, descripción, canónica e imagen para compartir propios.

use App\Models\Edicion;

function seoEdicion(string $nombre = '21° Edición Torneos Mita\'í'): Edicion
{
    return Edicion::create(['nombre' => $nombre]);
}

it('titula la home con la edicion y describe el torneo', function (): void {
    seoEdicion();

    $this->get('/')->assertOk()
        ->assertSee('<title>'.e("21° Edición Torneos Mita'í · Fixture, resultados y tablas").'</title>', false)
        ->assertSee('<meta name="description" content="Fixture, resultados, tablas de posiciones y goleadores de la 21° Edición Torneos Mita&#039;í, torneo de fútbol infantil en Posadas, Misiones.">', false)
        ->assertSee('<link rel="canonical" href="'.url('/').'">', false)
        ->assertSee('<meta property="og:image" content="'.asset('og-torneos-mitai.jpg').'">', false)
        ->assertSee('<meta property="og:locale" content="es_AR">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
});

it('usa un titulo y una descripcion propios en cada pagina publica', function (string $url, string $titulo): void {
    seoEdicion();

    $this->get($url)->assertOk()
        ->assertSee('<title>'.e($titulo).'</title>', false)
        ->assertSee('<meta property="og:title" content="'.e($titulo).'">', false);
})->with([
    'fixture' => ['/fixture', "Fixture y resultados · 21° Edición Torneos Mita'í"],
    'posiciones' => ['/tabla-de-posiciones', "Tabla de posiciones · 21° Edición Torneos Mita'í"],
    'goleadores' => ['/tabla-de-goleadores', "Tabla de goleadores · 21° Edición Torneos Mita'í"],
    'vallas' => ['/tabla-de-vallas', "Vallas menos vencidas · 21° Edición Torneos Mita'í"],
    'campeones' => ['/campeones', "Campeones · 21° Edición Torneos Mita'í"],
    'instancias finales' => ['/instancias-finales', "Instancias finales · 21° Edición Torneos Mita'í"],
    'terminos' => ['/terminos-y-condiciones', "Términos y condiciones · Torneos Mita'í"],
]);

it('nombra la edicion elegida y la conserva en la canonica sin los filtros de presentacion', function (): void {
    seoEdicion();
    $anterior = seoEdicion('20° Edición Torneos Mita\'í');

    $this->get('/fixture?estado=proximos&orden=asc&idEdicion='.$anterior->id)->assertOk()
        ->assertSee('<title>'.e("Fixture y resultados · 20° Edición Torneos Mita'í").'</title>', false)
        ->assertSee('<link rel="canonical" href="'.url('/fixture').'?idEdicion='.$anterior->id.'">', false);
});
