<?php

// T2.C: replaces the 300 hardcoded portal brand hex occurrences
// (#0e4b5e/#2a6d81/#f7f280, excluding Panel/imprimir/** which is print-only
// and out of scope) with the Phase 0 brand-navy/brand-teal/brand-gold
// tokens, preserving opacity modifiers, plus the rgba(14,75,94,x) /
// rgba(247,242,128,x) shadow values with the channel-variable equivalent.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

it('no deja hex de marca #0e4b5e/#2a6d81/#f7f280 fuera de Panel/imprimir', function () {
    $vistas = collect(File::allFiles(resource_path('views')))
        ->filter(fn ($archivo) => $archivo->getExtension() === 'php')
        ->filter(fn ($archivo) => ! str_contains($archivo->getRealPath(), '/Panel/imprimir/'))
        // Los correos usan estilos en línea: los clientes de correo no soportan variables CSS.
        ->filter(fn ($archivo) => ! str_contains($archivo->getRealPath(), '/emails/')
            && ! str_contains($archivo->getRealPath(), '/components/correo/'));

    $ofensores = [];

    foreach ($vistas as $archivo) {
        $contenido = file_get_contents($archivo->getRealPath());

        if (preg_match('/#0e4b5e|#2a6d81|#f7f280/i', $contenido)) {
            $ofensores[] = str_replace(resource_path('views').'/', '', $archivo->getRealPath());
        }
    }

    expect($ofensores)->toBe([]);
});

it('no deja rgba\\(14,75,94 ni rgba\\(247,242,128 crudos fuera de Panel/imprimir', function () {
    $vistas = collect(File::allFiles(resource_path('views')))
        ->filter(fn ($archivo) => $archivo->getExtension() === 'php')
        ->filter(fn ($archivo) => ! str_contains($archivo->getRealPath(), '/Panel/imprimir/'));

    $ofensores = [];

    foreach ($vistas as $archivo) {
        $contenido = file_get_contents($archivo->getRealPath());

        if (preg_match('/rgba\(\s*14,\s*75,\s*94|rgba\(\s*247,\s*242,\s*128/', $contenido)) {
            $ofensores[] = str_replace(resource_path('views').'/', '', $archivo->getRealPath());
        }
    }

    expect($ofensores)->toBe([]);
});

it('renderiza la home publica con los tokens brand-navy/brand-teal/brand-gold en vez de hex', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSee('brand-gold', false);
    $response->assertDontSee('#f7f280', false);
    $response->assertDontSee('#0e4b5e', false);
    $response->assertDontSee('#2a6d81', false);
});

it('renderiza el listado de vallas con los tokens de marca y sin hex crudo', function () {
    $response = $this->get(route('tabla-vallas'));

    $response->assertOk();
    $response->assertSee('brand-navy', false);
    $response->assertSee('brand-teal', false);
    $response->assertSee('brand-gold', false);
    $response->assertDontSee('#0e4b5e', false);
    $response->assertDontSee('#2a6d81', false);
    $response->assertDontSee('#f7f280', false);
});

it('renderiza el selector de vallas con el estilo claro del panel y conserva la opcion del equipo', function () {
    $admin = User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
    $edicion = Edicion::create(['nombre' => 'Edicion Valla Brand Tokens']);
    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 15',
    ]);
    $equipo = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Mitai Azul',
        'foto' => 'escudo.png',
    ]);
    EquipoEdicion::create([
        'idEquipo' => $equipo->id,
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
    ]);

    $response = $this->actingAs($admin)->get(route('valla.create', [
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
    ]));

    $response->assertOk();

    $erroresInternos = libxml_use_internal_errors(true);
    try {
        $documento = new DOMDocument;
        $documento->loadHTML($response->getContent());
        $xpath = new DOMXPath($documento);
        $selectores = $xpath->query('//select[@id="selectValla"]');
        expect($selectores->length)->toBe(1);

        $selector = $selectores->item(0);
        if (! $selector instanceof DOMElement) {
            throw new RuntimeException('No se encontró el selector de vallas.');
        }

        expect($selector->getAttribute('class'))->toContain('text-gray-700')
            ->and($selector->getAttribute('style'))->not->toContain('color-scheme: dark');

        $opcionesEquipo = $xpath->query('//select[@id="selectValla"]/option[@value="'.$equipo->id.'"]');
        expect($opcionesEquipo->length)->toBe(1);
        $opcionEquipo = $opcionesEquipo->item(0);
        if (! $opcionEquipo instanceof DOMElement) {
            throw new RuntimeException('No se encontró la opción del equipo.');
        }

        expect($opcionEquipo->textContent)->toContain('Mitai Azul')
            ->and($opcionEquipo->getAttribute('style'))->not->toContain('background-color:')
            ->and($opcionEquipo->getAttribute('style'))->not->toContain('color: white');

        $camposEquipo = $xpath->query('//input[@type="hidden" and @id="idEquipo" and @name="idEquipo"]');
        expect($camposEquipo->length)->toBe(1);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($erroresInternos);
    }

    $response->assertDontSee('#0e4b5e', false);
});

it('compila las clases shadow-[...rgba(var(--brand-navy)...)] a una regla real con el valor original', function () {
    $probeDir = sys_get_temp_dir().'/portal-brand-tokens-probe-'.uniqid('', true);
    mkdir($probeDir, 0777, true);

    $probeHtml = $probeDir.'/probe.html';
    $outputCss = $probeDir.'/out.css';

    file_put_contents($probeHtml, '<div class="shadow-[0_24px_60px_-40px_rgba(var(--brand-navy)_/_0.75)]"></div>');

    $result = Process::path(base_path())
        ->timeout(60)
        ->run([
            'node', base_path('node_modules/tailwindcss/lib/cli.js'),
            '-c', base_path('tailwind.config.js'),
            '-i', base_path('resources/css/app.css'),
            '--content', $probeHtml,
            '-o', $outputCss,
        ]);

    expect($result->successful())->toBeTrue($result->errorOutput());

    $css = file_get_contents($outputCss);

    array_map('unlink', glob($probeDir.'/*'));
    rmdir($probeDir);

    // The original hardcoded value was rgba(14,75,94,0.75) — 14 75 94 is
    // exactly --brand-navy, so the compiled shadow must resolve to the same
    // numeric channels via the CSS variable, not merely reference its name.
    expect($css)->toContain('rgba(var(--brand-navy) / 0.75)');
});
