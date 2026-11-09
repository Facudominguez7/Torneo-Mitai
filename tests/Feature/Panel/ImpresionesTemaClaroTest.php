<?php

function tokensImpresionTemaClaro(string $bloque): array
{
    preg_match_all('/(--pa-[a-z0-9-]+)\s*:\s*([^;{}]+);/', $bloque, $declaraciones, PREG_SET_ORDER);
    $tokens = [];

    foreach ($declaraciones as $declaracion) {
        expect($tokens)->not->toHaveKey($declaracion[1]);
        $tokens[$declaracion[1]] = trim($declaracion[2]);
    }

    expect($tokens)->not->toBeEmpty();
    ksort($tokens);

    return $tokens;
}

function tokensBaseImpresionTemaClaro(string $css): array
{
    expect(preg_match('/(?<![\w-]):root\s*\{([^{}]*)\}/s', $css, $base))->toBe(1);

    return tokensImpresionTemaClaro($base[1]);
}

it('fija en la vista previa todos los tokens del panel a sus valores claros', function (): void {
    $css = file_get_contents(resource_path('css/app.css'));
    expect(preg_match('/(?<![\w-])\.imprimible\s*\{([^{}]*)\}/s', $css, $imprimible))->toBe(1);

    expect(tokensImpresionTemaClaro($imprimible[1]))->toBe(tokensBaseImpresionTemaClaro($css));
});

it('fija al imprimir todos los tokens claros para los temas explicitos y el sistema oscuro', function (): void {
    $css = file_get_contents(resource_path('css/app.css'));
    expect(preg_match('/@media\s+print\s*\{\s*([^{}]*:root[^{}]*)\{([^{}]*)\}/s', $css, $impresion))->toBe(1);
    $selectores = preg_split('/\s*,\s*/', trim($impresion[1]));

    expect($selectores)->toContain(':root')
        ->toContain(':root:not([data-tema])')
        ->toContain(':root[data-tema="claro"]')
        ->toContain(':root[data-tema="oscuro"]')
        ->and(tokensImpresionTemaClaro($impresion[2]))->toBe(tokensBaseImpresionTemaClaro($css));
});

it('renderiza cada impresion de cobros con la clase imprimible en el body', function (string $vista): void {
    $this->withoutVite();
    $datos = [
        'EdicionSeleccionada' => (object) ['id' => 1, 'nombre' => 'Edición de prueba'],
        'filtros' => [],
        'categorias' => collect(),
        'equipos' => collect(),
        'fechas' => collect(),
        'resumen' => array_fill_keys([
            'deuda_ars', 'eximido_ars', 'recaudacion_asociada_ars', 'ingresos_ars',
            'credito_ars', 'deudores', 'exenciones',
        ], 0) + ['ingresos_por_medio' => []],
        'cargos' => collect(),
        'nombreEquipo' => 'Equipo de prueba',
        'nombreCategoria' => 'Categoría de prueba',
        'saldo_ars' => 0,
        'debe' => collect(),
        'al_dia' => collect(),
        'deudores' => collect(),
    ];
    $html = view('Panel.cobros.'.$vista, $datos)->render();
    $documento = new DOMDocument;
    expect($documento->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING))->toBeTrue();
    $body = $documento->getElementsByTagName('body')->item(0);

    expect($body)->not->toBeNull()
        ->and(preg_split('/\s+/', trim($body->getAttribute('class'))))->toContain('imprimible');
})->with(['imprimir', 'informe-equipo', 'informe-deudores']);

it('mantiene las otras impresiones autocontenidas sin el CSS compartido', function (string $vista): void {
    $contenido = file_get_contents(resource_path('views/Panel/'.$vista.'.blade.php'));

    expect($contenido)->toContain('<style>')
        ->not->toMatch('/@vite\b|@extends\b|@include\b|@import\b|<link\b|app\.css/i');
})->with([
    'imprimir/fixture',
    'imprimir/fixture-arbitros',
    'imprimir/fixture-arbitros-partidos',
    'imprimir/fixture-instancias-finales',
    'imprimir/tabla-posiciones',
    'lista-buena-fe/imprimir',
    'lista-buena-fe/imprimir-todas',
    'lista-buena-fe/imprimir-fecha',
]);
