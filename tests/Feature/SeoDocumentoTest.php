<?php

// Las páginas públicas se declaran HTML5 en español: sin doctype el navegador usa el modo quirks.

use App\Models\Edicion;

it('declara el doctype y el idioma en cada pagina publica', function (string $ruta): void {
    $edicion = Edicion::create(['nombre' => '21° Edición Torneos Mita\'í']);

    $html = $this->get(str_replace(':id', (string) $edicion->id, $ruta))->assertOk()->getContent();

    expect(ltrim($html))->toStartWith("<!DOCTYPE html>\n<html lang=\"es\">");
})->with([
    '/', '/fixture?idEdicion=:id', '/tabla-de-posiciones?idEdicion=:id', '/tabla-de-goleadores?idEdicion=:id',
    '/tabla-de-vallas?idEdicion=:id', '/goleadores?idEdicion=:id', '/vallas?idEdicion=:id', '/campeones?idEdicion=:id',
    '/subcampeones?idEdicion=:id', '/instancias-finales', '/login', '/terminos-y-condiciones',
]);
