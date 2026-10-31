<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\VallaMenosVencida;

function crearVallaPremiadaDiseno(Edicion $edicion, string $arquero, string $categoria, string $equipo, string $foto, ?int $goles): void
{
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => $categoria]);
    $equipo = Equipo::create([
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'nombre' => $equipo,
        'foto' => $foto,
    ]);

    VallaMenosVencida::create([
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'idEquipo' => $equipo->id,
        'nombre' => $arquero,
    ]);

    if ($goles !== null) {
        EquipoEdicion::create([
            'idEdicion' => $edicion->id,
            'idCategoria' => $categoria->id,
            'idEquipo' => $equipo->id,
            'golesContra' => $goles,
        ]);
    }
}

function xpathVallasPremiadasDiseno(string $html): DOMXPath
{
    $documento = new DOMDocument;
    @$documento->loadHTML('<?xml encoding="UTF-8">'.$html);

    return new DOMXPath($documento);
}

it('muestra arqueros premiados con escudos y goles de sus equipos en orden descendente de categoría', function () {
    $edicion = Edicion::create(['nombre' => 'Edición Arqueros']);
    crearVallaPremiadaDiseno($edicion, 'Luz Arco', 'Sub 10', 'Club Sauce', 'sauce-valla.png', 7);
    crearVallaPremiadaDiseno($edicion, 'Mateo Arco', 'Sub 16', 'Club Tala', 'tala-valla.png', 0);
    crearVallaPremiadaDiseno($edicion, 'Alma Arco', 'Sub 12', 'Club Cedro', 'cedro-valla.png', null);

    $respuesta = $this->get(route('vallas', ['idEdicion' => $edicion->id]))->assertOk();
    $xpath = xpathVallasPremiadasDiseno($respuesta->getContent());
    $articulos = $xpath->query('//main//article');

    expect($articulos->length)->toBe(3)
        ->and($articulos->item(0)->textContent)->toContain('Mateo Arco')->toContain('Sub 16')->toContain('Club Tala')
        ->and($articulos->item(1)->textContent)->toContain('Alma Arco')->toContain('Sub 12')->toContain('Club Cedro')
        ->and($articulos->item(2)->textContent)->toContain('Luz Arco')->toContain('Sub 10')->toContain('Club Sauce');

    foreach ([['Mateo Arco', 'Club Tala', 'tala-valla.png', 0], ['Alma Arco', 'Club Cedro', 'cedro-valla.png', 0], ['Luz Arco', 'Club Sauce', 'sauce-valla.png', 7]] as [$arquero, $equipo, $foto, $goles]) {
        $articulo = $xpath->query('//main//article[.//h3[normalize-space()="'.$arquero.'"]]')->item(0);
        expect($articulo)->not->toBeNull()
            ->and($xpath->query('.//img[@src="'.asset('fotos/equipos/'.$foto).'" and @alt="'.$equipo.'"]', $articulo)->length)->toBe(1)
            ->and(preg_replace('/\s+/u', ' ', $articulo->textContent))->toMatch('/\b'.$goles.'\s+goles recibidos\b/iu');
    }

    expect($xpath->query('//main//*[contains(concat(" ", normalize-space(@class), " "), " premios-tablero ")]')->length)->toBeGreaterThan(0)
        ->and($xpath->query('//main//article[contains(concat(" ", normalize-space(@class), " "), " premios-tarjeta ")]')->length)->toBe(3)
        ->and($respuesta->getContent())->not->toContain('blur-3xl');
});

it('filtra los arqueros premiados por edición y mantiene distinta la tabla de vallas', function () {
    $primera = Edicion::create(['nombre' => 'Edición Vallas Primera']);
    $segunda = Edicion::create(['nombre' => 'Edición Vallas Segunda']);
    crearVallaPremiadaDiseno($primera, 'Nora Primera', 'Sub 11', 'Club Ceibo', 'ceibo-valla.png', 3);
    crearVallaPremiadaDiseno($segunda, 'Bruno Segundo', 'Sub 15', 'Club Ombú', 'ombu-valla.png', 9);

    foreach ([[$primera, 'Nora Primera', 'Bruno Segundo'], [$segunda, 'Bruno Segundo', 'Nora Primera']] as [$edicion, $visible, $oculto]) {
        $this->get(route('vallas', ['idEdicion' => $edicion->id]))
            ->assertOk()->assertSee($visible)->assertDontSee($oculto);
    }

    expect(route('vallas'))->not->toBe(route('tabla-vallas'));
});

it('conserva el estado vacío sin edición y en una edición sin arqueros premiados', function () {
    $edicion = Edicion::create(['nombre' => 'Edición Vallas Vacía']);

    foreach ([route('vallas'), route('vallas', ['idEdicion' => $edicion->id])] as $url) {
        $respuesta = $this->get($url)->assertOk()->assertSee('Sin registros para la edición seleccionada.');
        expect(xpathVallasPremiadasDiseno($respuesta->getContent())->query('//main//article')->length)->toBe(0);
    }
});

it('ofrece menú de ediciones accesible y selector GET alternativo para vallas premiadas', function () {
    $primera = Edicion::create(['nombre' => 'Edición Menú Vallas Primera']);
    $segunda = Edicion::create(['nombre' => 'Edición Menú Vallas Segunda']);

    foreach ([$primera, $segunda] as $seleccionada) {
        $respuesta = $this->get(route('vallas', ['idEdicion' => $seleccionada->id]))->assertOk();
        $xpath = xpathVallasPremiadasDiseno($respuesta->getContent());
        $menu = '//main//details[contains(concat(" ", normalize-space(@class), " "), " premios-selector ")]';
        $formulario = '//main//form[translate(@method, "get", "GET")="GET" and @action="'.route('vallas').'"]';
        $selector = $formulario.'//select[@name="idEdicion" and @id="idEdicion"]';

        expect($xpath->query($menu.'/summary[contains(., "'.$seleccionada->nombre.'")]')->length)->toBe(1)
            ->and($xpath->query($menu.'//a[@href="'.route('vallas', ['idEdicion' => $primera->id]).'" and contains(., "'.$primera->nombre.'")]')->length)->toBe(1)
            ->and($xpath->query($menu.'//a[@href="'.route('vallas', ['idEdicion' => $segunda->id]).'" and contains(., "'.$segunda->nombre.'")]')->length)->toBe(1)
            ->and($xpath->query($menu.'//a[@href="'.route('vallas', ['idEdicion' => $seleccionada->id]).'" and @aria-current="page"]')->length)->toBe(1)
            ->and($xpath->query($selector.'/option[@value="'.$seleccionada->id.'" and @selected]')->length)->toBe(1)
            ->and($xpath->query($selector.'/option[@value="'.$primera->id.'"]')->length)->toBe(1)
            ->and($xpath->query($selector.'/option[@value="'.$segunda->id.'"]')->length)->toBe(1)
            ->and($xpath->query('//main//label[@for="idEdicion"]')->length)->toBe(1)
            ->and($xpath->query($formulario.'//button[not(@type) or @type="submit"] | '.$formulario.'//input[@type="submit"]')->length)->toBeGreaterThan(0);
    }
});
