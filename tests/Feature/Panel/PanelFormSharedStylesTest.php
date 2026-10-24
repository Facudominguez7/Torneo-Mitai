<?php

// T2.B: the ~46 Panel/**/_form|create|edit.blade.php files repeat the exact
// same long Tailwind class string (with #6A64F1/#6B7280/#e0e0e0 hardcoded)
// on every <input>/<select>/submit-button. This test proves the collapse
// into shared .panel-form-input/.panel-form-input-static/.panel-form-submit
// classes, backed by tokens instead of raw hex, without changing the
// rendered visual result.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\TablaGoleador;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

it('no deja hex crudo #6A64F1/#6B7280/#e0e0e0 en los formularios del Panel', function () {
    $archivos = collect(File::allFiles(resource_path('views/Panel')))
        ->filter(fn ($archivo) => preg_match('/(_form|create|edit)\.blade\.php$/', $archivo->getFilename()));

    $ofensores = [];

    foreach ($archivos as $archivo) {
        $contenido = file_get_contents($archivo->getRealPath());

        if (preg_match('/#6A64F1|#6B7280|#e0e0e0/i', $contenido)) {
            $ofensores[] = str_replace(resource_path('views').'/', '', $archivo->getRealPath());
        }
    }

    expect($ofensores)->toBe([]);
});

it('renderiza tabla_goleador/edit con las clases compartidas panel-form-input y panel-form-input-static', function () {
    $admin = User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
    $edicion = Edicion::create(['nombre' => 'Edicion Tabla Goleador Styles']);
    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 13',
    ]);
    $equipo = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Mitai Azul',
        'foto' => 'escudo.png',
    ]);
    $jugador = Jugador::create([
        'nombre' => 'Juan',
        'apellido' => 'Perez',
        'dni' => '30111222',
    ]);
    $goleador = TablaGoleador::create([
        'idEquipo' => $equipo->id,
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'dni_jugador' => $jugador->dni,
        'cantidadGoles' => 5,
    ]);

    $response = $this->actingAs($admin)->get(route('tabla_goleador.edit', $goleador));

    $response->assertOk();

    // El campo Nombre (editable) usa panel-form-input; el campo Equipo
    // (readonly, "automatico segun jugador") usa panel-form-input-static
    // porque el markup original nunca tuvo estilo de foco ahi.
    $response->assertSee('class="panel-form-input"', false);
    $response->assertSee('class="panel-form-input-static"', false);
    $response->assertDontSee('#6A64F1', false);
    $response->assertDontSee('#6B7280', false);
    $response->assertDontSee('#e0e0e0', false);
});

it('renderiza categoria/create con el boton de envio de x-ui.button (T3: form-card)', function () {
    // T3 (panel-admin-redesign) migró categoria/create a x-ui.form-card, cuya
    // barra de acciones usa x-ui.button en vez del <button class="panel-form-submit">
    // legado — el mismo botón brand-tokenizado que ya usan Editar/Detalle/Eliminar
    // en el listado. panel-form-submit sigue vigente para los ~45 formularios del
    // Panel que T3 no tocó (ver el resto de este archivo).
    $admin = User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
    $edicion = Edicion::create(['nombre' => 'Edicion Panel Form Styles']);

    $response = $this->actingAs($admin)->get(route('categoria.create', ['idEdicion' => $edicion->id]));

    $response->assertOk();
    $response->assertSee('bg-brand-navy', false);
    $response->assertSee('>Agregar<', false);
    $response->assertDontSee('#6A64F1', false);
});

it('compila panel-form-input y panel-form-submit a reglas CSS reales con los tokens del panel', function () {
    $probeDir = sys_get_temp_dir().'/panel-form-styles-probe-'.uniqid('', true);
    mkdir($probeDir, 0777, true);

    $probeHtml = $probeDir.'/probe.html';
    $outputCss = $probeDir.'/out.css';

    file_put_contents($probeHtml, '<div class="panel-form-input panel-form-input-static panel-form-submit"></div>');

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

    // #e0e0e0 = rgb(224 224 224), #6A64F1 = rgb(106 100 241) — Tailwind
    // compiles named colors to their decimal RGB channels, not the hex
    // literal, so this is the real proof the tokens resolve to the exact
    // original values rather than reading the config as text.
    expect($css)->toContain('.panel-form-input')
        ->and($css)->toContain('.panel-form-submit')
        ->and($css)->toContain('rgb(224 224 224')
        ->and($css)->toContain('rgb(106 100 241');
});
