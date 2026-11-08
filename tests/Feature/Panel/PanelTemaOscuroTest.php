<?php

use App\Models\User;

function documentoTemaOscuroPanel(string $html): DOMDocument
{
    $documento = new DOMDocument;
    expect($documento->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING))->toBeTrue();

    return $documento;
}

function bloqueTokensTemaPanel(string $css, string $patron): string
{
    preg_match_all($patron, $css, $coincidencias);
    $bloques = array_values(array_filter(
        $coincidencias[1],
        fn (string $bloque): bool => str_contains($bloque, '--pa-'),
    ));
    expect($bloques)->toHaveCount(1);

    return $bloques[0];
}

it('renderiza el panel con el tema del sistema y sin script de tema en linea', function () {
    $admin = User::factory()->create(['rol' => 'admin', 'email_verified_at' => now()]);
    $html = $this->actingAs($admin)->get(route('admin'))->assertOk()->getContent();
    $documento = documentoTemaOscuroPanel($html);
    $raiz = $documento->getElementsByTagName('html')->item(0);

    expect($raiz)->not->toBeNull()
        ->and($raiz->hasAttribute('data-tema'))->toBeFalse();

    foreach ($documento->getElementsByTagName('script') as $script) {
        if (! $script->hasAttribute('src')) {
            expect($script->textContent)->not->toMatch('/mitai_tema|data-tema|btn-tema|prefers-color-scheme/');
        }
    }
});

it('incluye un boton de tema accesible con los ids del tema compartido', function () {
    $admin = User::factory()->create(['rol' => 'admin', 'email_verified_at' => now()]);
    $html = $this->actingAs($admin)->get(route('admin'))->assertOk()->getContent();
    $documento = documentoTemaOscuroPanel($html);
    $boton = $documento->getElementById('btn-tema');

    expect($boton)->not->toBeNull()
        ->and($boton->tagName)->toBe('button')
        ->and($boton->getAttribute('type'))->toBe('button')
        ->and($boton->hasAttribute('aria-label'))->toBeFalse()
        ->and($boton->getAttribute('class'))->toMatch('/(?:^|\s)(?:(?:min-)?h-11|min-h-\[44px\])(?:\s|$)/')
        ->and($boton->getAttribute('class'))->toMatch('/(?:^|\s)(?:(?:min-)?w-11|min-w-\[44px\])(?:\s|$)/');

    $xpath = new DOMXPath($documento);
    $iconos = $xpath->query('.//*[@id="icono-tema"]', $boton);
    $etiquetas = $xpath->query('.//*[@id="etiqueta-tema"]', $boton);
    $prefijos = $xpath->query('.//*[@id="etiqueta-tema"]/preceding-sibling::*[1][self::span[contains(concat(" ", normalize-space(@class), " "), " sr-only ") and normalize-space(.)="Tema:"]]', $boton);

    expect($iconos->length)->toBe(1)
        ->and($etiquetas->length)->toBe(1)
        ->and($prefijos->length)->toBe(1)
        ->and(trim($etiquetas->item(0)->textContent))->toBe('Sistema')
        ->and($etiquetas->item(0)->getAttribute('class'))->not->toContain('hidden');

    expect(file_get_contents(resource_path('js/app.js')))->toContain("import './tema';");
});

it('declara todos los tokens del panel con sus valores exactos en los cuatro estados', function () {
    $css = file_get_contents(resource_path('css/app.css'));
    $claro = [
        'fondo' => '243 244 246',
        'superficie' => '255 255 255',
        'elevada' => '255 255 255',
        'tinta' => '17 24 39',
        'tinta-media' => '51 65 85',
        'tinta-cuerpo' => '75 85 99',
        'tinta-suave' => '107 114 128',
        'tinta-tenue' => '156 163 175',
        'campo-fondo' => '255 255 255',
        'control-fondo' => '241 245 249',
        'acento' => '42 109 129',
        'resaltado' => '14 75 94',
        'aviso-fondo' => '147 197 253',
        'aviso-tinta' => '17 24 39',
        'form-borde' => '224 224 224',
        'form-acento' => '106 100 241',
        'form-tinta' => '255 255 255',
        'tablero-inicio' => '29 78 216',
        'tablero-fin' => '14 116 144',
        'tablero-leyenda' => '219 234 254',
        'tablero-foco' => '59 130 246',
        'tablero-accion' => '29 78 216',
        'tablero-accion-hover' => '30 64 175',
        'contorno-control' => '203 213 225',
        'velo-menu' => '15 23 42',
        'velo-modal' => '2 6 23',
        'linea' => '229 231 235',
        'linea-alfa' => '1',
        'linea-suave' => '226 232 240',
        'linea-suave-alfa' => '1',
        'campo-borde' => '209 213 219',
        'campo-borde-alfa' => '1',
        'fila-bg' => '249 250 251',
        'fila-bg-alfa' => '1',
        'fila-hover' => '243 244 246',
        'fila-hover-alfa' => '1',
    ];
    $oscuro = [
        'fondo' => '8 47 59',
        'superficie' => '14 75 94',
        'elevada' => '14 75 94',
        'tinta' => '255 255 255',
        'tinta-media' => '255 255 255',
        'tinta-cuerpo' => '255 255 255',
        'tinta-suave' => '255 255 255',
        'tinta-tenue' => '255 255 255',
        'campo-fondo' => '8 47 59',
        'control-fondo' => '8 47 59',
        'acento' => '155 211 226',
        'resaltado' => '155 211 226',
        'aviso-fondo' => '155 211 226',
        'aviso-tinta' => '8 47 59',
        'form-borde' => '155 211 226',
        'form-acento' => '155 211 226',
        'form-tinta' => '8 47 59',
        'tablero-inicio' => '14 75 94',
        'tablero-fin' => '8 47 59',
        'tablero-leyenda' => '226 232 240',
        'tablero-foco' => '155 211 226',
        'tablero-accion' => '42 109 129',
        'tablero-accion-hover' => '8 47 59',
        'contorno-control' => '155 211 226',
        'velo-menu' => '8 47 59',
        'velo-modal' => '8 47 59',
        'linea' => '255 255 255',
        'linea-alfa' => '.14',
        'linea-suave' => '255 255 255',
        'linea-suave-alfa' => '.10',
        'campo-borde' => '255 255 255',
        'campo-borde-alfa' => '.22',
        'fila-bg' => '255 255 255',
        'fila-bg-alfa' => '.06',
        'fila-hover' => '255 255 255',
        'fila-hover-alfa' => '.12',
    ];
    $estados = [
        ['~:root\s*\{([^{}]*)\}~s', $claro],
        ['~@media\s*\(prefers-color-scheme:\s*dark\)\s*\{\s*:root:not\(\[data-tema="claro"\]\)\s*\{([^{}]*)\}~s', $oscuro],
        ['~:root\[data-tema="claro"\]\s*\{([^{}]*)\}~s', $claro],
        ['~:root\[data-tema="oscuro"\]\s*\{([^{}]*)\}~s', $oscuro],
    ];

    foreach ($estados as [$patron, $esperados]) {
        $bloque = bloqueTokensTemaPanel($css, $patron);
        preg_match_all('/--pa-([a-z-]+)\s*:\s*([^;{}]+);/', $bloque, $declaraciones, PREG_SET_ORDER);
        $valores = [];
        foreach ($declaraciones as $declaracion) {
            $valores[$declaracion[1]] = trim($declaracion[2]);
        }
        foreach ($esperados as $nombre => $valor) {
            expect($valores[$nombre] ?? null)->toBe($valor);
        }
        $declarados = array_keys($valores);
        $permitidos = array_keys($esperados);
        sort($declarados);
        sort($permitidos);
        expect($declarados)->toBe($permitidos)
            ->and($declaraciones)->toHaveCount(count($permitidos));
    }
});

it('elimina los tokens retirados y el prefijo anterior del tablero', function () {
    $archivos = [
        file_get_contents(resource_path('css/app.css')),
        file_get_contents(base_path('tailwind.config.js')),
    ];
    $retirados = ['tinta-titulo', 'tinta-marca', 'tinta-menu', 'tinta-secundaria', 'tinta-etiqueta', 'mensaje-tinta'];

    foreach ($archivos as $archivo) {
        foreach ($retirados as $token) {
            expect($archivo)->not->toContain('--pa-'.$token);
        }
        expect($archivo)->not->toContain('--pa-fixture-');
    }
});

it('deja todas las letras en blanco en oscuro y conserva la escala en claro', function () {
    $css = file_get_contents(resource_path('css/app.css'));
    $oscuros = [
        '~@media\s*\(prefers-color-scheme:\s*dark\)\s*\{\s*:root:not\(\[data-tema="claro"\]\)\s*\{([^{}]*)\}~s',
        '~:root\[data-tema="oscuro"\]\s*\{([^{}]*)\}~s',
    ];
    $claros = [
        '~:root\s*\{([^{}]*)\}~s',
        '~:root\[data-tema="claro"\]\s*\{([^{}]*)\}~s',
    ];
    $nombres = ['tinta', 'tinta-media', 'tinta-cuerpo', 'tinta-suave', 'tinta-tenue'];

    // Decisión del dueño: en oscuro no hay tonos apagados, todas las letras son blancas.
    foreach ($oscuros as $patron) {
        $bloque = bloqueTokensTemaPanel($css, $patron);
        foreach ($nombres as $nombre) {
            preg_match('/--pa-'.preg_quote($nombre, '/').'\s*:\s*([^;{}]+);/', $bloque, $coincidencia);
            expect(trim($coincidencia[1] ?? ''))->toBe('255 255 255');
        }
    }

    // En claro se conserva la jerarquía original de cinco pasos.
    foreach ($claros as $patron) {
        $bloque = bloqueTokensTemaPanel($css, $patron);
        $tonos = [];
        foreach ($nombres as $nombre) {
            preg_match('/--pa-'.preg_quote($nombre, '/').'\s*:\s*([^;{}]+);/', $bloque, $coincidencia);
            expect($coincidencia)->toHaveCount(2);
            $tonos[] = trim($coincidencia[1]);
        }
        expect(array_unique($tonos))->toHaveCount(5);
    }
});

it('adapta los estilos compartidos de formularios a los tokens del panel', function () {
    $css = file_get_contents(resource_path('css/app.css'));
    $config = file_get_contents(base_path('tailwind.config.js'));
    preg_match('/\.panel-form-input\s*,\s*\.panel-form-input-static\s*\{([^{}]*)\}/s', $css, $campos);
    preg_match('/\.panel-form-submit\s*\{([^{}]*)\}/s', $css, $boton);
    preg_match('/\bpanel\s*:\s*\{([^{}]*)\}/s', $config, $panel);
    $estiloCampos = $campos[1] ?? '';
    $estiloBoton = $boton[1] ?? '';
    $coloresPanel = $panel[1] ?? '';

    expect($estiloCampos)->toContain('bg-pa-campo-fondo')
        ->toContain('text-pa-tinta-suave')
        ->not->toContain('bg-white')
        ->not->toContain('text-gray-500');

    if (str_contains($estiloCampos, 'border-panel-border')) {
        expect($coloresPanel)->toMatch('/border\s*:\s*[\'"][^\'"]*--pa-/');
    } else {
        expect($estiloCampos)->toContain('border-pa-');
    }

    if (str_contains($estiloBoton, 'bg-panel-accent')) {
        expect($coloresPanel)->toMatch('/accent\s*:\s*[\'"][^\'"]*--pa-/');
    } else {
        expect($estiloBoton)->toContain('bg-pa-');
    }
});

it('mapea los tokens opacos y translucidos del panel en Tailwind', function () {
    $config = file_get_contents(base_path('tailwind.config.js'));
    preg_match('/\bpa\s*:\s*\{([^{}]*)\}/s', $config, $coincidencias);
    $mapa = $coincidencias[1] ?? '';
    $opacos = [
        'fondo', 'superficie', 'elevada', 'campo-fondo', 'control-fondo',
        'tinta', 'tinta-media', 'tinta-cuerpo', 'tinta-suave', 'tinta-tenue',
        'acento', 'resaltado', 'aviso-fondo', 'aviso-tinta', 'form-borde', 'form-acento', 'form-tinta',
        'tablero-inicio', 'tablero-fin', 'tablero-leyenda', 'tablero-foco',
        'tablero-accion', 'tablero-accion-hover', 'contorno-control', 'velo-menu', 'velo-modal',
    ];
    $translucidos = ['linea', 'linea-suave', 'campo-borde', 'fila-bg', 'fila-hover'];

    foreach ($opacos as $nombre) {
        $valor = "rgb(var(--pa-{$nombre}) / <alpha-value>)";
        expect($mapa)->toMatch('/(?:^|[\s,])[\'"]?'.preg_quote($nombre, '/').'[\'"]?\s*:\s*[\'"]'.preg_quote($valor, '/').'[\'"]/');
    }
    foreach ($translucidos as $nombre) {
        $valor = "rgb(var(--pa-{$nombre}) / var(--pa-{$nombre}-alfa))";
        expect($mapa)->toMatch('/(?:^|[\s,])[\'"]?'.preg_quote($nombre, '/').'[\'"]?\s*:\s*[\'"]'.preg_quote($valor, '/').'[\'"]/');
    }

    preg_match_all('/(?:^|[\s,])(?:[\'"]([^\'"]+)[\'"]|([a-z][a-z0-9-]*))\s*:/', $mapa, $coincidencias, PREG_SET_ORDER);
    $declarados = array_map(fn (array $coincidencia): string => $coincidencia[1] !== '' ? $coincidencia[1] : $coincidencia[2], $coincidencias);
    $permitidos = array_merge($opacos, $translucidos);
    sort($declarados);
    sort($permitidos);
    expect($declarados)->toBe($permitidos);
});
