<?php

use App\Models\Categoria;
use App\Models\Copa;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Subcampeon;

function crearSubcampeonDiseno(Edicion $edicion, string $copa, string $nombre, string $categoria, string $foto): void
{
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => $categoria]);
    $equipo = Equipo::create([
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'nombre' => $nombre,
        'foto' => $foto,
    ]);
    $copa = Copa::firstOrCreate(['nombre' => $copa]);

    Subcampeon::create([
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'idEquipo' => $equipo->id,
        'idCopa' => $copa->id,
    ]);
}

function xpathSubcampeonesDiseno(string $html): DOMXPath
{
    $documento = new DOMDocument;
    @$documento->loadHTML('<?xml encoding="UTF-8">'.$html);

    return new DOMXPath($documento);
}

it('presenta las cuatro copas de subcampeones con sus categorías equipos y escudos reales', function () {
    $edicion = Edicion::create(['nombre' => 'Edición Subcampeones Diseño']);
    $copas = [
        ['Oro', 'Copa de Oro', 'Club Alerce', 'Sub 11', 'alerce.png'],
        ['Plata', 'Copa de Plata', 'Club Ceibo', 'Sub 12', 'ceibo.png'],
        ['Bronce', 'Copa de Bronce', 'Club Lapacho', 'Sub 13', 'lapacho.png'],
        ['Mita', "Copa Mita'í", 'Club Jacarandá', 'Sub 14', 'jacaranda.png'],
    ];

    foreach ($copas as [, $copa, $equipo, $categoria, $foto]) {
        crearSubcampeonDiseno($edicion, $copa, $equipo, $categoria, $foto);
    }

    $respuesta = $this->get(route('subcampeones', ['idEdicion' => $edicion->id]))->assertOk();
    $xpath = xpathSubcampeonesDiseno($respuesta->getContent());
    $secciones = $xpath->query('//main//section[.//h2]');

    expect($xpath->query('//main//h1[contains(., "Subcampeones")]')->length)->toBe(1)
        ->and($secciones->length)->toBe(4);

    foreach ($copas as $indice => [$titulo, $copa, $equipo, $categoria, $foto]) {
        $seccion = $secciones->item($indice);
        expect($xpath->query('.//h2[contains(., "'.$titulo.'")]', $seccion)->length)->toBe(1);
        $articulos = $xpath->query('.//article[.//h3[normalize-space()="'.$equipo.'"]]', $seccion);
        expect($articulos->length)->toBe(1);
        $articulo = $articulos->item(0);
        expect($articulo->textContent)->toContain($categoria)->toContain($copa)
            ->and($xpath->query('.//img[@src="'.asset('fotos/equipos/'.$foto).'" and @alt="'.$equipo.'"]', $articulo)->length)->toBe(1);
    }

    expect($xpath->query('//main//*[contains(concat(" ", normalize-space(@class), " "), " campeones-oro ")]')->length)->toBeGreaterThan(0)
        ->and($respuesta->getContent())->not->toContain('blur-3xl');
});

it('filtra subcampeones por edición y ofrece enlaces del menú con selección actual', function () {
    $primera = Edicion::create(['nombre' => 'Edición Subcampeones Primera']);
    $segunda = Edicion::create(['nombre' => 'Edición Subcampeones Segunda']);
    crearSubcampeonDiseno($primera, 'Copa de Oro', 'Club Sauce', 'Sub 10', 'sauce.png');
    crearSubcampeonDiseno($segunda, 'Copa de Oro', 'Club Tala', 'Sub 16', 'tala.png');

    foreach ([[$primera, 'Club Sauce', 'Club Tala'], [$segunda, 'Club Tala', 'Club Sauce']] as [$edicion, $visible, $oculto]) {
        $respuesta = $this->get(route('subcampeones', ['idEdicion' => $edicion->id]))
            ->assertOk()->assertSee($visible)->assertDontSee($oculto);
        $xpath = xpathSubcampeonesDiseno($respuesta->getContent());
        $menu = '//main//details[@class and summary]';

        expect($xpath->query($menu.'/summary[contains(., "'.$edicion->nombre.'")]')->length)->toBe(1)
            ->and($xpath->query($menu.'//a[@href="'.route('subcampeones', ['idEdicion' => $primera->id]).'" and contains(., "'.$primera->nombre.'")]')->length)->toBe(1)
            ->and($xpath->query($menu.'//a[@href="'.route('subcampeones', ['idEdicion' => $segunda->id]).'" and contains(., "'.$segunda->nombre.'")]')->length)->toBe(1)
            ->and($xpath->query($menu.'//a[@href="'.route('subcampeones', ['idEdicion' => $edicion->id]).'" and @aria-current="page"]')->length)->toBe(1);
    }
});

it('permite elegir una edición con formulario GET nativo sin JavaScript', function () {
    $primera = Edicion::create(['nombre' => 'Edición Alternativa Primera']);
    $segunda = Edicion::create(['nombre' => 'Edición Alternativa Segunda']);
    $respuesta = $this->get(route('subcampeones', ['idEdicion' => $segunda->id]))->assertOk();
    $xpath = xpathSubcampeonesDiseno($respuesta->getContent());
    $formulario = '//main//form[translate(@method, "get", "GET")="GET" and @action="'.route('subcampeones').'"]';
    $selector = $formulario.'//select[@name="idEdicion" and @id="idEdicion"]';

    expect($xpath->query($formulario.'//label[@for="idEdicion"]')->length)->toBe(1)
        ->and($xpath->query($selector.'/option[@value="'.$primera->id.'"]')->length)->toBe(1)
        ->and($xpath->query($selector.'/option[@value="'.$segunda->id.'" and @selected]')->length)->toBe(1)
        ->and($xpath->query($formulario.'//button[not(@type) or @type="submit"] | '.$formulario.'//input[@type="submit"]')->length)->toBeGreaterThan(0);
});

it('ordena las categorías de cada copa en forma descendente', function () {
    $edicion = Edicion::create(['nombre' => 'Edición Orden Subcampeones']);
    crearSubcampeonDiseno($edicion, 'Copa de Oro', 'Club Menor', 'Sub 10', 'menor.png');
    crearSubcampeonDiseno($edicion, 'Copa de Oro', 'Club Mayor', 'Sub 16', 'mayor.png');
    crearSubcampeonDiseno($edicion, 'Copa de Oro', 'Club Medio', 'Sub 13', 'medio.png');

    $respuesta = $this->get(route('subcampeones', ['idEdicion' => $edicion->id]))->assertOk();
    $xpath = xpathSubcampeonesDiseno($respuesta->getContent());
    $articulos = $xpath->query('//main//section[.//h2[contains(., "Oro")]]//article//h3');

    expect($articulos->length)->toBe(3)
        ->and(trim($articulos->item(0)->textContent))->toBe('Club Mayor')
        ->and(trim($articulos->item(1)->textContent))->toBe('Club Medio')
        ->and(trim($articulos->item(2)->textContent))->toBe('Club Menor');
});

it('conserva los estados vacíos sin selección y en copas sin equipos', function () {
    $edicion = Edicion::create(['nombre' => 'Edición Subcampeones Vacía']);
    $mensaje = 'No se registraron equipos en esta copa para la edición seleccionada.';

    foreach ([route('subcampeones'), route('subcampeones', ['idEdicion' => $edicion->id])] as $url) {
        $respuesta = $this->get($url)->assertOk()->assertSee($mensaje);
        $xpath = xpathSubcampeonesDiseno($respuesta->getContent());
        expect($xpath->query('//main//article')->length)->toBe(0)
            ->and(substr_count($respuesta->getContent(), $mensaje))->toBe(4);
    }

    crearSubcampeonDiseno($edicion, 'Copa de Plata', 'Club Único', 'Sub 12', 'unico.png');
    $respuesta = $this->get(route('subcampeones', ['idEdicion' => $edicion->id]))->assertOk()->assertSee('Club Único');
    expect(substr_count($respuesta->getContent(), $mensaje))->toBe(3);
});
