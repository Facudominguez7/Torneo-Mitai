<?php

use App\Models\User;

function crearAdminPanelShell(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

it('renderiza la navegacion agrupada con las cinco etiquetas de grupo', function () {
    $admin = crearAdminPanelShell();

    $response = $this->actingAs($admin)->get(route('admin'));

    $response->assertOk();
    $response->assertSee('Hoy', false);
    $response->assertSee('Torneo', false);
    $response->assertSee('Equipos y jugadores', false);
    $response->assertSee('Estadísticas', false);
    $response->assertSee('Comunicación', false);
});

it('incluye el enlace a Partidos en la navegacion', function () {
    $admin = crearAdminPanelShell();

    $response = $this->actingAs($admin)->get(route('admin'));

    $response->assertOk();
    $response->assertSee(route('partido.index'), false);
    $response->assertSee('Partidos', false);
});

it('no incluye Equipos por edicion en la navegacion', function () {
    $admin = crearAdminPanelShell();

    $response = $this->actingAs($admin)->get(route('admin'));

    $response->assertOk();
    $response->assertDontSee('Equipos por edici', false);
    $response->assertDontSee(route('equipoedicion.index'), false);
});

it('marca aria-current en el item activo de un modulo', function () {
    $admin = crearAdminPanelShell();

    $response = $this->actingAs($admin)->get(route('categoria.index'));

    $response->assertOk();
    $html = $response->getContent();

    // El enlace a Categorias debe tener aria-current="page".
    expect($html)->toMatch('/href="'.preg_quote(route('categoria.index'), '/').'"[^>]*aria-current="page"/');
});

it('abre por defecto el grupo que contiene la ruta activa', function () {
    $admin = crearAdminPanelShell();

    $response = $this->actingAs($admin)->get(route('categoria.index'));

    $response->assertOk();
    $html = $response->getContent();

    // El grupo "Torneo" (que contiene Categorias) debe iniciar abierto.
    preg_match('/data-grupo-nav="torneo"[^>]*data-grupo-abierto="([a-z]+)"/', $html, $coincidencia);
    expect($coincidencia[1] ?? null)->toBe('true');

    // Un grupo sin la ruta activa (Comunicacion) debe iniciar cerrado.
    preg_match('/data-grupo-nav="comunicacion"[^>]*data-grupo-abierto="([a-z]+)"/', $html, $coincidenciaCerrado);
    expect($coincidenciaCerrado[1] ?? null)->toBe('false');
});

it('el grupo Hoy siempre inicia abierto', function () {
    $admin = crearAdminPanelShell();

    $response = $this->actingAs($admin)->get(route('categoria.index'));

    $response->assertOk();
    $html = $response->getContent();

    preg_match('/data-grupo-nav="hoy"[^>]*data-grupo-abierto="([a-z]+)"/', $html, $coincidencia);
    expect($coincidencia[1] ?? null)->toBe('true');
});

it('muestra el selector de edicion en el topbar', function () {
    $admin = crearAdminPanelShell();

    $response = $this->actingAs($admin)->get(route('admin'));

    $response->assertOk();
    $response->assertSee('name="idEdicion"', false);
    $response->assertSee('id="panel-edicion-select"', false);
});

it('elimina la barra de Seleccionar contexto', function () {
    $admin = crearAdminPanelShell();

    $response = $this->actingAs($admin)->get(route('admin'));

    $response->assertOk();
    $response->assertDontSee('Seleccionar contexto', false);
    $response->assertDontSee('selector-contexto-form', false);
});

it('expone los controles del drawer movil con sus aria-label', function () {
    $admin = crearAdminPanelShell();

    $response = $this->actingAs($admin)->get(route('admin'));

    $response->assertOk();
    $response->assertSee('aria-label="Abrir menú"', false);
    $response->assertSee('aria-label="Cerrar menú"', false);
});

it('el body ya no usa el shell oscuro legacy', function () {
    $admin = crearAdminPanelShell();

    $response = $this->actingAs($admin)->get(route('admin'));

    $response->assertOk();
    $html = $response->getContent();

    preg_match('/<body[^>]*>/', $html, $coincidencia);
    $etiquetaBody = $coincidencia[0] ?? '';

    expect($etiquetaBody)->not->toContain('color-primary')
        ->and($etiquetaBody)->not->toContain('Poppins')
        ->and($etiquetaBody)->toContain('bg-gray-100');
});

it('conserva categoria y grupo actuales en el enlace a la tabla de posiciones', function () {
    $admin = crearAdminPanelShell();

    $response = $this->actingAs($admin)->get(route('tabla_posicion.index', ['idCategoria' => 5, 'idGrupo' => 7]));

    $response->assertOk();
    expect($response->getContent())->toMatch('/href="[^"]*tabla_posicion[^"]*idCategoria=5[^"]*idGrupo=7[^"]*"/');
});
