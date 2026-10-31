<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Goleador;

function crearGoleadorPremiadoDiseno(Edicion $edicion, string $jugador, string $categoria, string $equipo, string $foto): void
{
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => $categoria]);
    $equipo = Equipo::create([
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'nombre' => $equipo,
        'foto' => $foto,
    ]);

    Goleador::create([
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'idEquipo' => $equipo->id,
        'nombre' => $jugador,
    ]);
}

function xpathGoleadoresPremiadosDiseno(string $html): DOMXPath
{
    $documento = new DOMDocument;
    @$documento->loadHTML('<?xml encoding="UTF-8">'.$html);

    return new DOMXPath($documento);
}

it('muestra los premiados con sus datos y escudos en orden descendente de categoría', function () {
    $edicion = Edicion::create(['nombre' => 'Edición Premiados']);
    crearGoleadorPremiadoDiseno($edicion, 'Lucía Cancha', 'Sub 10', 'Club Sauce', 'sauce-premiado.png');
    crearGoleadorPremiadoDiseno($edicion, 'Mateo Cancha', 'Sub 16', 'Club Tala', 'tala-premiado.png');

    $respuesta = $this->get(route('goleadores', ['idEdicion' => $edicion->id]))->assertOk()
        ->assertSee('Goleadores del torneo');
    $xpath = xpathGoleadoresPremiadosDiseno($respuesta->getContent());
    $articulos = $xpath->query('//main//article');

    expect($articulos->length)->toBe(2)
        ->and($articulos->item(0)->textContent)->toContain('Mateo Cancha')->toContain('Sub 16')->toContain('Club Tala')
        ->and($articulos->item(1)->textContent)->toContain('Lucía Cancha')->toContain('Sub 10')->toContain('Club Sauce');

    foreach ([['Mateo Cancha', 'Club Tala', 'tala-premiado.png'], ['Lucía Cancha', 'Club Sauce', 'sauce-premiado.png']] as [$jugador, $equipo, $foto]) {
        $articulo = $xpath->query('//main//article[.//h3[normalize-space()="'.$jugador.'"]]')->item(0);
        expect($articulo)->not->toBeNull()
            ->and($xpath->query('.//img[@src="'.asset('fotos/equipos/'.$foto).'" and @alt="'.$equipo.'"]', $articulo)->length)->toBe(1);
    }

    expect($xpath->query('//main//*[contains(concat(" ", normalize-space(@class), " "), " premios-tablero ")]')->length)->toBeGreaterThan(0)
        ->and($xpath->query('//main//article[contains(concat(" ", normalize-space(@class), " "), " premios-tarjeta ")]')->length)->toBe(2)
        ->and($respuesta->getContent())->not->toContain('blur-3xl');
});

it('filtra premiados por edición sin consultar la tabla de goleadores', function () {
    $primera = Edicion::create(['nombre' => 'Edición Premios Primera']);
    $segunda = Edicion::create(['nombre' => 'Edición Premios Segunda']);
    crearGoleadorPremiadoDiseno($primera, 'Alma Primera', 'Sub 11', 'Club Cedro', 'cedro.png');
    crearGoleadorPremiadoDiseno($segunda, 'Bruno Segundo', 'Sub 15', 'Club Ceibo', 'ceibo.png');

    foreach ([[$primera, 'Alma Primera', 'Bruno Segundo'], [$segunda, 'Bruno Segundo', 'Alma Primera']] as [$edicion, $visible, $oculto]) {
        $this->get(route('goleadores', ['idEdicion' => $edicion->id]))
            ->assertOk()->assertSee($visible)->assertDontSee($oculto);
    }
});

it('conserva el estado vacío sin edición y en una edición sin premiados', function () {
    $edicion = Edicion::create(['nombre' => 'Edición Premios Vacía']);

    foreach ([route('goleadores'), route('goleadores', ['idEdicion' => $edicion->id])] as $url) {
        $respuesta = $this->get($url)->assertOk()->assertSee('Sin registros para la edición seleccionada.');
        $xpath = xpathGoleadoresPremiadosDiseno($respuesta->getContent());
        expect($xpath->query('//main//article')->length)->toBe(0);
    }
});

it('ofrece menú de ediciones accesible y selector GET alternativo para premiados', function () {
    $primera = Edicion::create(['nombre' => 'Edición Menú Premios Primera']);
    $segunda = Edicion::create(['nombre' => 'Edición Menú Premios Segunda']);

    foreach ([$primera, $segunda] as $seleccionada) {
        $respuesta = $this->get(route('goleadores', ['idEdicion' => $seleccionada->id]))->assertOk();
        $xpath = xpathGoleadoresPremiadosDiseno($respuesta->getContent());
        $menu = '//main//details[contains(concat(" ", normalize-space(@class), " "), " premios-selector ")]';
        $formulario = '//main//form[translate(@method, "get", "GET")="GET" and @action="'.route('goleadores').'"]';
        $selector = $formulario.'//select[@name="idEdicion" and @id="idEdicion"]';

        expect($xpath->query($menu.'/summary[contains(., "'.$seleccionada->nombre.'")]')->length)->toBe(1)
            ->and($xpath->query($menu.'//a[@href="'.route('goleadores', ['idEdicion' => $primera->id]).'" and contains(., "'.$primera->nombre.'")]')->length)->toBe(1)
            ->and($xpath->query($menu.'//a[@href="'.route('goleadores', ['idEdicion' => $segunda->id]).'" and contains(., "'.$segunda->nombre.'")]')->length)->toBe(1)
            ->and($xpath->query($menu.'//a[@href="'.route('goleadores', ['idEdicion' => $seleccionada->id]).'" and @aria-current="page"]')->length)->toBe(1)
            ->and($xpath->query($selector.'/option[@value="'.$seleccionada->id.'" and @selected]')->length)->toBe(1)
            ->and($xpath->query($selector.'/option[@value="'.$primera->id.'"]')->length)->toBe(1)
            ->and($xpath->query($selector.'/option[@value="'.$segunda->id.'"]')->length)->toBe(1)
            ->and($xpath->query('//main//label[@for="idEdicion"]')->length)->toBe(1)
            ->and($xpath->query($formulario.'//button[not(@type) or @type="submit"] | '.$formulario.'//input[@type="submit"]')->length)->toBeGreaterThan(0);
    }
});
