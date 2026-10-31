<?php

use App\Models\Campeon;
use App\Models\Categoria;
use App\Models\Copa;
use App\Models\Edicion;
use App\Models\Equipo;

function crearCampeonDiseno(Edicion $edicion, string $copa, string $nombre, string $categoria, string $foto): void
{
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => $categoria]);
    $equipo = Equipo::create([
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'nombre' => $nombre,
        'foto' => $foto,
    ]);
    $copa = Copa::firstOrCreate(['nombre' => $copa]);

    Campeon::create([
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'idEquipo' => $equipo->id,
        'idCopa' => $copa->id,
    ]);
}

function xpathCampeones(string $html): DOMXPath
{
    $documento = new DOMDocument;
    @$documento->loadHTML('<?xml encoding="UTF-8">'.$html);

    return new DOMXPath($documento);
}

it('agrupa los campeones de las cuatro copas con categoría y escudo de la edición elegida', function () {
    $edicion = Edicion::create(['nombre' => 'Edición Prueba Uno']);
    $copas = [
        ['Copa de Oro', 'Club Alerce', 'Sub 11', 'alerce.png'],
        ['Copa de Plata', 'Club Ceibo', 'Sub 12', 'ceibo.png'],
        ['Copa de Bronce', 'Club Lapacho', 'Sub 13', 'lapacho.png'],
        ["Copa Mita'í", 'Club Jacarandá', 'Sub 14', 'jacaranda.png'],
    ];

    foreach ($copas as [$copa, $equipo, $categoria, $foto]) {
        crearCampeonDiseno($edicion, $copa, $equipo, $categoria, $foto);
    }

    $respuesta = $this->get(route('campeones', ['idEdicion' => $edicion->id]))->assertOk();
    $xpath = xpathCampeones($respuesta->getContent());

    foreach ($copas as [$copa, $equipo, $categoria, $foto]) {
        $articulos = $xpath->query('//main//article[h3[normalize-space()="'.$equipo.'"]]');
        expect($articulos->length)->toBe(1);
        $articulo = $articulos->item(0);
        expect($articulo->textContent)->toContain($categoria)->toContain($copa)
            ->and($xpath->query('.//img[contains(@src, "'.$foto.'") and @alt="'.$equipo.'"]', $articulo)->length)->toBe(1);
    }
});

it('conserva el selector GET y cambia los resultados al elegir otra edición', function () {
    $primera = Edicion::create(['nombre' => 'Edición Prueba Primera']);
    $segunda = Edicion::create(['nombre' => 'Edición Prueba Segunda']);
    crearCampeonDiseno($primera, 'Copa de Oro', 'Club Sauce', 'Sub 10', 'sauce.png');
    crearCampeonDiseno($segunda, 'Copa de Oro', 'Club Tala', 'Sub 16', 'tala.png');

    foreach ([[$primera, 'Club Sauce', 'Club Tala'], [$segunda, 'Club Tala', 'Club Sauce']] as [$edicion, $visible, $oculto]) {
        $respuesta = $this->get(route('campeones', ['idEdicion' => $edicion->id]))
            ->assertOk()->assertSee($visible)->assertDontSee($oculto);
        $xpath = xpathCampeones($respuesta->getContent());
        $selector = '//form[translate(@method, "get", "GET")="GET" and @action="'.route('campeones').'"]//select[@name="idEdicion" and @id="idEdicion"]';
        expect($xpath->query($selector)->length)->toBe(1)
            ->and($xpath->query($selector.'[contains(@onchange, "submit")]')->length)->toBe(1)
            ->and($xpath->query('//label[@for="idEdicion"]')->length)->toBe(1)
            ->and($xpath->query($selector.'/option[@value="'.$edicion->id.'" and @selected]')->length)->toBe(1)
            ->and($xpath->query($selector.'/option[@value="'.$primera->id.'"]')->length)->toBe(1)
            ->and($xpath->query($selector.'/option[@value="'.$segunda->id.'"]')->length)->toBe(1);
    }
});

it('muestra el estado vacío sin edición y para cada copa sin campeones', function () {
    $edicion = Edicion::create(['nombre' => 'Edición Prueba Vacía']);

    foreach ([route('campeones'), route('campeones', ['idEdicion' => $edicion->id])] as $url) {
        $respuesta = $this->get($url)->assertOk()
            ->assertSee('No se registraron equipos en esta copa para la edición seleccionada.');
        $xpath = xpathCampeones($respuesta->getContent());
        expect($xpath->query('//main//article')->length)->toBe(0)
            ->and(substr_count($respuesta->getContent(), 'No se registraron equipos en esta copa para la edición seleccionada.'))->toBe(4);
    }
});

it('presenta la jerarquía editorial con secciones semánticas en orden y oro destacado', function () {
    $edicion = Edicion::create(['nombre' => 'Edición Prueba Jerarquía']);
    foreach (['Copa de Oro', 'Copa de Plata', 'Copa de Bronce', "Copa Mita'í"] as $indice => $copa) {
        crearCampeonDiseno($edicion, $copa, 'Club Diseño '.($indice + 1), 'Categoría '.($indice + 1), 'diseno-'.$indice.'.png');
    }

    $respuesta = $this->get(route('campeones', ['idEdicion' => $edicion->id]))->assertOk();
    $xpath = xpathCampeones($respuesta->getContent());
    $secciones = $xpath->query('//main//section[h2]');

    expect($xpath->query('//main//h1')->length)->toBe(1)
        ->and($secciones->length)->toBe(4);
    foreach (['Oro', 'Plata', 'Bronce', 'Mita'] as $indice => $copa) {
        $seccion = $secciones->item($indice);
        expect($xpath->query('./h2[contains(., "'.$copa.'")]', $seccion)->length)->toBe(1)
            ->and($xpath->query('.//article//h3', $seccion)->length)->toBe(1);
    }
    expect($xpath->query('//main//*[contains(concat(" ", normalize-space(@class), " "), " campeones-oro ")]')->length)->toBeGreaterThan(0)
        ->and($respuesta->getContent())->not->toContain('blur-3xl');
});

it('ofrece un menú de ediciones estilizado y conserva el selector GET nativo', function () {
    $primera = Edicion::create(['nombre' => 'Edición Menú Primera']);
    $segunda = Edicion::create(['nombre' => 'Edición Menú Segunda']);

    foreach ([$primera, $segunda] as $seleccionada) {
        $respuesta = $this->get(route('campeones', ['idEdicion' => $seleccionada->id]))->assertOk();
        $xpath = xpathCampeones($respuesta->getContent());
        $menu = '//details[summary and @class]';
        $selector = '//form[translate(@method, "get", "GET")="GET" and @action="'.route('campeones').'"]//select[@name="idEdicion" and @id="idEdicion"]';

        expect($xpath->query($menu.'/summary[contains(., "'.$seleccionada->nombre.'")]')->length)->toBe(1)
            ->and($xpath->query($menu.'//a[@href="'.route('campeones', ['idEdicion' => $primera->id]).'" and contains(., "'.$primera->nombre.'")]')->length)->toBe(1)
            ->and($xpath->query($menu.'//a[@href="'.route('campeones', ['idEdicion' => $segunda->id]).'" and contains(., "'.$segunda->nombre.'")]')->length)->toBe(1)
            ->and($xpath->query($menu.'//a[@href="'.route('campeones', ['idEdicion' => $seleccionada->id]).'" and @aria-current="page"]')->length)->toBe(1)
            ->and($xpath->query($selector.'/option[@value="'.$seleccionada->id.'" and @selected]')->length)->toBe(1)
            ->and($xpath->query('//label[@for="idEdicion"]')->length)->toBe(1);
    }
});
