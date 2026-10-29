<?php

it('presenta la planilla como tablas oficiales y reportes separados', function () {
    $html = view('components.planilla-publica-modal')->render();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    expect($xpath->query('//div[@role="dialog"]')->length)->toBe(1)
        ->and($xpath->query('//table[thead/tr/th]')->length)->toBeGreaterThan(0)
        ->and($html)->toContain('clave: \'local\'', 'clave: \'visitante\'', 'goles_arbitros', 'motivo_inconsistencia', 'planillaCargando', 'planillaError', 'sin_planilla');
});

it('monta el modal fuera de los contextos de apilamiento y permite cerrar desde el fondo', function () {
    $html = view('components.planilla-publica-modal')->render();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    expect($xpath->query('//template[@x-teleport="body"]//div[@role="dialog"]')->length)->toBe(1)
        ->and($xpath->query('//template[@x-teleport="body"]//div[contains(@class,"planilla-publica__posicion")][@*[name()="x-on:click.self" and .="cerrarPlanilla()"]]')->length)->toBe(1)
        ->and($html)->toContain('atraparFocoPlanilla($event)', 'this.planillaOrigen?.focus()');
});
