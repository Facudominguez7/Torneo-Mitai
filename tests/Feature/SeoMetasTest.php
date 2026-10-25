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

it('usa un titulo propio con la edicion elegida en cada pagina publica', function (string $ruta, string $seccion): void {
    $edicion = seoEdicion();
    $titulo = "{$seccion} · 21° Edición Torneos Mita'í";

    $this->get($ruta.'?idEdicion='.$edicion->id)->assertOk()
        ->assertSee('<title>'.e($titulo).'</title>', false)
        ->assertSee('<meta property="og:title" content="'.e($titulo).'">', false);
})->with([
    'fixture' => ['/fixture', 'Fixture y resultados'],
    'posiciones' => ['/tabla-de-posiciones', 'Tabla de posiciones'],
    'goleadores' => ['/tabla-de-goleadores', 'Tabla de goleadores'],
    'vallas' => ['/tabla-de-vallas', 'Vallas menos vencidas'],
    'campeones' => ['/campeones', 'Campeones'],
    'instancias finales' => ['/instancias-finales', 'Instancias finales'],
]);

it('no nombra una edicion cuando la pagina solo muestra el selector', function (): void {
    seoEdicion();

    $this->get('/campeones')->assertOk()
        ->assertSee('<title>'.e("Campeones · Torneos Mita'í").'</title>', false)
        ->assertSee('<meta name="description" content="'.e("Los campeones de cada copa y categoría en todas las ediciones de Torneos Mita'í, fútbol infantil en Posadas, Misiones.").'">', false)
        ->assertDontSee('21° Edición Torneos Mita&#039;í · ', false);
});

it('nombra la ultima edicion en las paginas que la muestran por defecto', function (): void {
    seoEdicion('20° Edición Torneos Mita\'í');
    seoEdicion();

    $this->get('/instancias-finales')->assertOk()
        ->assertSee('<title>'.e("Instancias finales · 21° Edición Torneos Mita'í").'</title>', false);
});

it('titula las paginas legales con el nombre del sitio', function (): void {
    $this->get('/terminos-y-condiciones')->assertOk()
        ->assertSee('<title>'.e("Términos y condiciones · Torneos Mita'í").'</title>', false);
});

it('nombra la edicion elegida y la conserva en la canonica sin los filtros de presentacion', function (): void {
    seoEdicion();
    $anterior = seoEdicion('20° Edición Torneos Mita\'í');

    $this->get('/fixture?estado=proximos&orden=asc&idEdicion='.$anterior->id)->assertOk()
        ->assertSee('<title>'.e("Fixture y resultados · 20° Edición Torneos Mita'í").'</title>', false)
        ->assertSee('<link rel="canonical" href="'.url('/fixture').'?idEdicion='.$anterior->id.'">', false);
});
