<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

function crearAdminLegacyButtons(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

it('renderiza editar, detalle y eliminar de categorias con x-ui.button', function () {
    $admin = crearAdminLegacyButtons();
    $edicion = Edicion::create(['nombre' => 'Edicion Legacy Categoria']);
    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 14',
    ]);

    $response = $this->actingAs($admin)->get(route('categoria.index', ['idEdicion' => $edicion->id]));

    $response->assertOk();

    // Editar -> variant success (bg-emerald-600), Detalle -> variant primary
    // (bg-brand-navy), Eliminar -> variant danger (bg-red-600). None of the
    // legacy .btn/.btn-editar/.btn-detalle/.btn-eliminar classes remain.
    $response->assertSee('bg-emerald-600', false);
    $response->assertSee('bg-brand-navy', false);
    $response->assertSee('bg-red-600', false);
    $response->assertDontSee('btn-editar', false);
    $response->assertDontSee('btn-detalle', false);
    $response->assertDontSee('btn-eliminar', false);
    $response->assertDontSee('class="btn ', false);
});

it('renderiza el boton de limpiar filtros de partidos con x-ui.button', function () {
    $admin = crearAdminLegacyButtons();
    $edicion = Edicion::create(['nombre' => 'Edicion Legacy Partido']);

    $response = $this->actingAs($admin)->get(route('partido.index', ['idEdicion' => $edicion->id]));

    $response->assertOk();
    $response->assertSee('id="reset-filters"', false);
    // Rediseño del panel (T5): limpiar filtros no es destructivo, así que el
    // botón deja de ser rojo y pasa a ser un link de texto.
    preg_match('/<button[^>]*id="reset-filters"[^>]*>/', $response->getContent(), $botonLimpiar);
    expect($botonLimpiar[0] ?? '')->not->toContain('bg-red-600');
    $response->assertDontSee('btn-eliminar', false);
});

it('no deja usos de las clases legacy .btn/.btn-editar/.btn-eliminar/.btn-detalle ni .card en las vistas', function () {
    $vistas = File::allFiles(resource_path('views'));

    $ofensores = [];

    foreach ($vistas as $archivo) {
        if ($archivo->getExtension() !== 'php') {
            continue;
        }

        $ruta = $archivo->getRealPath();

        // El README de x-ui documenta el patron legacy a proposito; no es un uso real.
        if (str_contains($ruta, 'components/ui/README')) {
            continue;
        }

        $contenido = file_get_contents($ruta);

        $tieneClaseLegacy = false;

        if (preg_match_all('/class="([^"]*)"/', $contenido, $coincidencias)) {
            foreach ($coincidencias[1] as $listaClases) {
                $clases = preg_split('/\s+/', trim($listaClases));

                // Coincidencia exacta de token: "card" o "btn" como clase
                // propia, nunca como parte de otro nombre (partido-card,
                // card-body, panel-action-button, etc.).
                if (in_array('card', $clases, true) || in_array('btn', $clases, true)) {
                    $tieneClaseLegacy = true;
                    break;
                }
            }
        }

        if ($tieneClaseLegacy
            || str_contains($contenido, 'btn-editar')
            || str_contains($contenido, 'btn-eliminar')
            || str_contains($contenido, 'btn-detalle')) {
            $ofensores[] = str_replace(resource_path('views').'/', '', $ruta);
        }
    }

    expect($ofensores)->toBe([]);
});

it('elimina las reglas CSS legacy .btn y .card de app.css', function () {
    $css = File::get(resource_path('css/app.css'));

    expect($css)->not->toContain('.btn {')
        ->and($css)->not->toContain('.btn-editar')
        ->and($css)->not->toContain('.btn-eliminar')
        ->and($css)->not->toContain('.btn-detalle')
        ->and($css)->not->toContain('.card {')
        ->and($css)->toContain('.panel-admin-content');
});

it('migra el card legacy del formulario de tabla_goleador preservando el resultado visual', function () {
    $html = Blade::render(
        File::get(resource_path('views/Panel/tabla_goleador/_form.blade.php')),
        [
            'goleador_t' => (object) [
                'nombre_completo' => 'Jugador de Prueba',
                'cantidadGoles' => 3,
                'idEquipo' => null,
                'jugador' => null,
                'equipo' => null,
            ],
            'idCategoria' => 1,
            'CategoriaSeleccionada' => (object) ['id' => 1, 'nombreCategoria' => 'Sub 14'],
            'EdicionSeleccionada' => (object) ['id' => 1, 'nombre' => 'Edicion X'],
            'equipos' => collect(),
        ]
    );

    expect($html)->toContain('p-6 rounded shadow-sm bg-gray-800 text-white mb-5')
        ->and($html)->not->toMatch('/class="card[\s"]/');
});
