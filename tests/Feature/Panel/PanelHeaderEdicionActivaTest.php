<?php

use App\Models\Edicion;
use App\Models\User;

function crearAdminHeaderEdicion(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function crearEdicionesHeaderEdicion(): array
{
    $activa = Edicion::create(['nombre' => 'Edicion Activa 2024']);
    $otra = Edicion::create(['nombre' => 'Edicion Otra 2023']);

    return [$activa, $otra];
}

it('muestra el header de edición activa con la edición de la query', function () {
    [$activa, $otra] = crearEdicionesHeaderEdicion();
    $admin = crearAdminHeaderEdicion();

    $response = $this->actingAs($admin)->get('/Panel/admin?idEdicion='.$activa->id);

    $response->assertSuccessful();
    $response->assertSee('data-panel-edicion-header', false);
    $response->assertSee('Edicion Activa 2024');
    $response->assertSee('Edicion Otra 2023');
    $response->assertSee('<option value="'.$activa->id.'" selected', false);
});

it('muestra la edición persistida en sesión cuando la query no la trae', function () {
    [$activa, $otra] = crearEdicionesHeaderEdicion();
    $admin = crearAdminHeaderEdicion();

    $this->actingAs($admin)
        ->withSession(['panel.idEdicion' => $activa->id])
        ->get('/Panel/admin')
        ->assertSuccessful()
        ->assertSee('data-panel-edicion-header', false)
        ->assertSee('Edicion Activa 2024')
        ->assertSee('<option value="'.$activa->id.'" selected', false);
});

it('muestra el estado vacío accesible cuando no hay edición activa', function () {
    $admin = crearAdminHeaderEdicion();

    $this->actingAs($admin)
        ->get('/Panel/admin')
        ->assertSuccessful()
        ->assertSee('data-panel-edicion-header', false)
        ->assertSee('Sin edición seleccionada');
});

it('no muestra una edición inválida como activa', function () {
    [$activa, $otra] = crearEdicionesHeaderEdicion();
    $admin = crearAdminHeaderEdicion();

    $response = $this->actingAs($admin)
        ->withSession(['panel.idEdicion' => $activa->id])
        ->get('/Panel/admin?idEdicion=999999');

    $response->assertSuccessful();
    $response->assertSee('Sin edición seleccionada');
    $response->assertDontSee('<option value="999999"', false);
    $this->assertNull(session('panel.idEdicion'));

    $html = $response->getContent();
    expect($html)->toContain('href="'.route('equipo.index'));
    expect($html)->not->toContain('idEdicion=999999');
});

it('el selector del header navega a admin solo con idEdicion', function () {
    [$activa, $otra] = crearEdicionesHeaderEdicion();
    $admin = crearAdminHeaderEdicion();

    $html = $this->actingAs($admin)
        ->get('/Panel/admin?idEdicion='.$activa->id.'&horario=10:00')
        ->assertSuccessful()
        ->getContent();

    expect($html)->toMatch('/<form[^>]*data-panel-edicion-form[^>]*>/');

    preg_match('/<form[^>]*data-panel-edicion-form[^>]*>(.*?)<\/form>/s', $html, $matches);
    $formulario = $matches[0] ?? '';

    expect($formulario)->toContain('action="'.route('admin').'"');
    expect($formulario)->toContain('name="idEdicion"');
    expect($formulario)->not->toContain('name="idCategoria"');
    expect($formulario)->not->toContain('name="idGrupo"');
    expect($formulario)->not->toContain('name="idFecha"');
    expect($formulario)->not->toContain('name="horario"');
});

it('mantiene usuario, perfil y logout en el header del panel', function () {
    [$activa, $otra] = crearEdicionesHeaderEdicion();
    $admin = crearAdminHeaderEdicion();

    $html = $this->actingAs($admin)
        ->get('/Panel/admin?idEdicion='.$activa->id)
        ->assertSuccessful()
        ->getContent();

    expect($html)->toContain('action="'.route('logout').'"');
    expect($html)->toContain(route('profile.edit'));
    expect($html)->toContain($admin->name);
});

it('un invitado no puede ver el header del panel', function () {
    $this->get('/Panel/admin')->assertRedirect('/login');
});

it('muestra exactamente un botón para abrir el menú lateral', function () {
    [$activa, $otra] = crearEdicionesHeaderEdicion();
    $admin = crearAdminHeaderEdicion();

    $html = $this->actingAs($admin)
        ->get('/Panel/admin?idEdicion='.$activa->id)
        ->assertSuccessful()
        ->getContent();

    expect(substr_count($html, 'data-drawer-toggle="sidebar-multi-level-sidebar"'))->toBe(1);
});

it('muestra el enlace de perfil visible y accesible en mobile', function () {
    [$activa, $otra] = crearEdicionesHeaderEdicion();
    $admin = crearAdminHeaderEdicion();

    $html = $this->actingAs($admin)
        ->get('/Panel/admin?idEdicion='.$activa->id)
        ->assertSuccessful()
        ->getContent();

    expect(preg_match('/<a[^>]*href="'.preg_quote(route('profile.edit'), '/').'"[^>]*>/', $html, $matches))->toBe(1);

    $etiquetaApertura = $matches[0];
    expect($etiquetaApertura)->not->toContain('hidden');
    expect(preg_match('/<a[^>]*href="'.preg_quote(route('profile.edit'), '/').'"[^>]*>(.*?)<\/a>/s', $html, $contenido))->toBe(1);
    expect(str_contains($contenido[1], 'aria-label=') || str_contains($contenido[1], $admin->name))->toBeTrue();
});

it('identifica el panel como Mitaí Admin en el header', function () {
    [$activa, $otra] = crearEdicionesHeaderEdicion();
    $admin = crearAdminHeaderEdicion();

    $html = $this->actingAs($admin)
        ->get('/Panel/admin?idEdicion='.$activa->id)
        ->assertSuccessful()
        ->getContent();

    expect($html)->toContain('Mitaí Admin');
    expect($html)->toContain('Mitaí Admin</span>');
});

it('muestra el header de edición en otra página del panel', function () {
    [$activa, $otra] = crearEdicionesHeaderEdicion();
    $admin = crearAdminHeaderEdicion();

    $html = $this->actingAs($admin)
        ->get('/Panel/lista-buena-fe?idEdicion='.$activa->id)
        ->assertSuccessful()
        ->getContent();

    expect($html)->toContain('data-panel-edicion-header');
    expect(substr_count($html, 'data-drawer-toggle="sidebar-multi-level-sidebar"'))->toBe(1);
    expect($html)->toContain('<option value="'.$activa->id.'" selected');
});

it('muestra un único selector de edición sin duplicados en sidebar ni tarjeta', function () {
    [$activa, $otra] = crearEdicionesHeaderEdicion();
    $admin = crearAdminHeaderEdicion();

    $html = $this->actingAs($admin)
        ->get('/Panel/admin?idEdicion='.$activa->id)
        ->assertSuccessful()
        ->getContent();

    expect(substr_count($html, 'data-panel-edicion-form'))->toBe(1);
    expect($html)->not->toContain('Seleccionar Edición');
    expect($html)->not->toContain('dropdown-example');
    expect(substr_count($html, 'Edicion Activa 2024'))->toBe(1);
    expect($html)->toContain('lg:left-64');
    expect($html)->toContain('flex-wrap');
});

it('separa el contenido del header con padding mobile mayor al desktop', function () {
    [$activa, $otra] = crearEdicionesHeaderEdicion();
    $admin = crearAdminHeaderEdicion();

    $html = $this->actingAs($admin)
        ->get('/Panel/admin?idEdicion='.$activa->id)
        ->assertSuccessful()
        ->getContent();

    // El wrapper principal reserva espacio bajo el header fixed en ambos breakpoints.
    expect(preg_match('/class="[^"]*\bpt-(?<mobile>\d+)[^"]*\blg:pt-(?<desktop>\d+)[^"]*"/', $html, $wrapper))->toBe(1);

    // Tailwind: 1 unidad = 0.25rem. Desktop cubre el header de h-14 (56px) más aire; mobile cubre el header envuelto en dos filas (~129px) más aire.
    expect((int) $wrapper['desktop'])->toBeGreaterThanOrEqual(14);
    expect((int) $wrapper['mobile'])->toBeGreaterThan((int) $wrapper['desktop']);
    expect((int) $wrapper['mobile'])->toBeGreaterThanOrEqual(36);
});

it('usa una familia tipográfica con fallback sans cuando Poppins no carga', function () {
    [$activa, $otra] = crearEdicionesHeaderEdicion();
    $admin = crearAdminHeaderEdicion();

    $html = $this->actingAs($admin)
        ->get('/Panel/admin?idEdicion='.$activa->id)
        ->assertSuccessful()
        ->getContent();

    // La declaración de fuente debe incluir un fallback genérico, no solo el nombre de la fuente.
    expect(preg_match('/font-\[Poppins,\s*(serif|sans-serif|system-ui[\w,-]*)\]/', $html))->toBe(1);
    expect($html)->not->toMatch('/font-\[Poppins\]/');
});

it('el header del panel no aparece en el portal público', function () {
    Edicion::create(['nombre' => 'Edicion Publica']);

    $this->get('/')
        ->assertSuccessful()
        ->assertDontSee('data-panel-edicion-header', false);
});
