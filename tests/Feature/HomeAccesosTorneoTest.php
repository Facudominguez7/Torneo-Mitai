<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;

function homeAccesosContexto(): Edicion
{
    $edicion = Edicion::create(['nombre' => 'Edición seleccionada']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 14']);
    Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Equipo de prueba',
        'foto' => 'equipo.png',
    ]);
    Edicion::create(['nombre' => 'Edición más reciente']);

    return $edicion;
}

it('ofrece premios y accesos existentes para la edición seleccionada', function () {
    $edicion = homeAccesosContexto();
    $html = $this->get(route('home', ['idEdicion' => $edicion->id]))->assertOk()->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $xpath = new DOMXPath($dom);

    foreach ([
        'campeones' => 'Campeones',
        'subcampeones' => 'Subcampeones',
        'goleadores' => 'Goleadores premiados',
        'vallas' => 'Vallas premiadas',
        'tabla-posiciones' => 'Tabla de posiciones',
        'tabla-goleadores' => 'Goleadores',
        'instancias-finales' => 'Finales',
        'tabla-vallas' => 'Vallas menos vencidas',
    ] as $ruta => $etiqueta) {
        $url = route($ruta, ['idEdicion' => $edicion->id]);
        $enlaces = $xpath->query('//section[@aria-label="Accesos al torneo"]//a[@href="'.$url.'"]');
        expect($enlaces->length)->toBe(1)
            ->and(trim($enlaces->item(0)->textContent))->toBe($etiqueta);
    }
});

it('separa el torneo en juego de los premios de la edición', function () {
    $edicion = homeAccesosContexto();
    $html = $this->get(route('home', ['idEdicion' => $edicion->id]))->assertOk()->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $xpath = new DOMXPath($dom);

    foreach (['Partidos y tablas' => ['tabla-posiciones', 'tabla-goleadores', 'instancias-finales', 'tabla-vallas'], 'Premios de la edición' => ['campeones', 'subcampeones', 'goleadores', 'vallas']] as $titulo => $rutas) {
        $grupo = $xpath->query('//section[@aria-label="Accesos al torneo"]//section[h3[normalize-space(.)="'.$titulo.'"]]');
        expect($grupo->length)->toBe(1);
        foreach ($rutas as $ruta) {
            $url = route($ruta, ['idEdicion' => $edicion->id]);
            expect($xpath->query('.//a[@href="'.$url.'"]', $grupo->item(0))->length)->toBe(1);
        }
        expect($xpath->query('.//a', $grupo->item(0))->length)->toBe(4);
    }
});

it('sugiere River Plate como ejemplo en el buscador', function () {
    $edicion = homeAccesosContexto();
    $html = $this->get(route('home', ['idEdicion' => $edicion->id]))->assertOk()->getContent();

    expect($html)->toContain('placeholder="Buscá tu equipo, ej: River Plate"')
        ->and($html)->not->toContain('placeholder="Buscá tu equipo, ej: Talleres"');
});

it('mantiene una sola invitación a explorar el torneo con equipos disponibles', function () {
    $edicion = homeAccesosContexto();
    $html = $this->get(route('home', ['idEdicion' => $edicion->id]))->assertOk()->getContent();

    expect($html)->toContain('id="seguir-equipo-bloque"')
        ->and(substr_count($html, 'O explorá todo el torneo'))->toBe(1)
        ->and($html)->not->toContain('También podés explorar todo el torneo abajo.');
});
