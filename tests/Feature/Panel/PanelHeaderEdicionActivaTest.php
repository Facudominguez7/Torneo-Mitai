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

it('omite perfil y logout del header del panel', function () {
    [$activa, $otra] = crearEdicionesHeaderEdicion();
    $admin = crearAdminHeaderEdicion();

    $html = $this->actingAs($admin)
        ->get('/Panel/admin?idEdicion='.$activa->id)
        ->assertSuccessful()
        ->getContent();

    expect(preg_match('/<header[^>]*data-panel-edicion-header.*?<\/header>/s', $html, $header))->toBe(1);

    expect($header[0])->not->toContain(route('profile.edit'));
    expect($header[0])->not->toContain('action="'.route('logout').'"');
    expect($header[0])->not->toContain('aria-label="Cuenta"');
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

it('mantiene el drawer mobile sin controles de cuenta en el header', function () {
    [$activa, $otra] = crearEdicionesHeaderEdicion();
    $admin = crearAdminHeaderEdicion();

    $html = $this->actingAs($admin)
        ->get('/Panel/admin?idEdicion='.$activa->id)
        ->assertSuccessful()
        ->getContent();

    expect(preg_match('/<header[^>]*data-panel-edicion-header.*?<\/header>/s', $html, $header))->toBe(1);
    expect(substr_count($header[0], 'data-drawer-toggle="sidebar-multi-level-sidebar"'))->toBe(1);
    expect($header[0])->not->toContain(route('profile.edit'));
    expect($header[0])->not->toContain('action="'.route('logout').'"');
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
    // El dashboard (T2) agrega un subtítulo "Edición activa: ..." intencional;
    // la edición ya no aparece solo en el selector del header sino también ahí.
    expect(substr_count($html, 'Edicion Activa 2024'))->toBe(2);
    expect($html)->toContain('lg:ml-64');
});

it('mantiene el header en flujo normal y un respiro vertical modesto bajo el header', function () {
    [$activa, $otra] = crearEdicionesHeaderEdicion();
    $admin = crearAdminHeaderEdicion();

    $html = $this->actingAs($admin)
        ->get('/Panel/admin?idEdicion='.$activa->id)
        ->assertSuccessful()
        ->getContent();

    expect(preg_match('/<header[^>]*data-panel-edicion-header.*?<\/header>/s', $html, $header))->toBe(1);
    expect($header[0])->toContain('lg:ml-64');
    expect($header[0])->not->toContain('fixed');
    expect($header[0])->not->toContain('sticky');
    expect($header[0])->not->toMatch('/(?:^|\s)(?:top-|inset-y-|lg:left-)/');

    expect($header[0])->toContain('w-full');
    expect(preg_match('/<div class="(?<classes>relative flex min-h-screen[^"]*)">/', $html, $wrapper))->toBe(1);
    expect($wrapper['classes'])->toContain('lg:pl-64');
    // Respiro normal-flow: gap chico para que las acciones no toquen el header translúcido,
    // sin volver a la compensación grande del header fijo (pt-16+ o variantes responsivas).
    expect($wrapper['classes'])->toContain('pt-4');
    expect($wrapper['classes'])->toContain('sm:pt-6');
    expect($wrapper['classes'])->not->toMatch('/(?:^|\s)(?:pt-(?:16|20|24|28|32|36|40|48)|md:pt-|lg:pt-|xl:pt-|2xl:pt-)/');
});

it('usa una superficie blanca y discreta para el header del panel', function () {
    [$activa, $otra] = crearEdicionesHeaderEdicion();
    $admin = crearAdminHeaderEdicion();

    $html = $this->actingAs($admin)
        ->get('/Panel/admin?idEdicion='.$activa->id)
        ->assertSuccessful()
        ->getContent();

    expect(preg_match('/<header[^>]*data-panel-edicion-header.*?<\/header>/s', $html, $header))->toBe(1);

    $bloqueHeader = $header[0];
    expect($bloqueHeader)->toContain('bg-white/90');
    expect($bloqueHeader)->toContain('backdrop-blur-sm');
    expect($bloqueHeader)->toContain('border-slate-200');
    expect($bloqueHeader)->not->toContain('bg-slate-900');
    expect($bloqueHeader)->not->toContain('text-white');
});

it('reemplaza el acento saturado por uno neutro y etiqueta la edición de forma visible', function () {
    [$activa, $otra] = crearEdicionesHeaderEdicion();
    $admin = crearAdminHeaderEdicion();

    $html = $this->actingAs($admin)
        ->get('/Panel/admin?idEdicion='.$activa->id)
        ->assertSuccessful()
        ->getContent();

    expect(preg_match('/<header[^>]*data-panel-edicion-header.*?<\/header>/s', $html, $header))->toBe(1);
    expect($header[0])->not->toContain('cyan');

    expect(preg_match('/<form[^>]*data-panel-edicion-form[^>]*>(.*?)<\/form>/s', $html, $matches))->toBe(1);

    $formulario = $matches[0];
    expect($formulario)->toContain('bg-slate-100');
    expect(preg_match('/<label[^>]*>Edición<\/label>/', $formulario))->toBe(1);
    expect($formulario)->not->toContain('sr-only');
});

it('usa la utilidad font-sans en vez del stack arbitrario de Poppins', function () {
    // T1 (rediseño del panel admin): el shell aprobado deja de declarar
    // `font-[Poppins,...]` en el <body> y usa la utilidad `font-sans` de
    // Tailwind (que ya resuelve a Figtree + el stack sans por defecto en
    // tailwind.config.js), así que la aserción de "fallback dentro de
    // Poppins" queda obsoleta: ya no hay ningún `font-[Poppins` que necesite
    // fallback.
    [$activa, $otra] = crearEdicionesHeaderEdicion();
    $admin = crearAdminHeaderEdicion();

    $html = $this->actingAs($admin)
        ->get('/Panel/admin?idEdicion='.$activa->id)
        ->assertSuccessful()
        ->getContent();

    expect(preg_match('/<body[^>]*class="[^"]*\bfont-sans\b[^"]*"/', $html))->toBe(1);
    expect($html)->not->toMatch('/font-\[Poppins/');
});

it('presenta un header espacioso sin una barra global de módulos', function () {
    [$activa, $otra] = crearEdicionesHeaderEdicion();
    $admin = crearAdminHeaderEdicion();

    $html = $this->actingAs($admin)
        ->get('/Panel/admin?idEdicion='.$activa->id)
        ->assertSuccessful()
        ->getContent();

    expect($html)->toContain('data-panel-header-primary-row');
    expect($html)->toContain('data-panel-header-edition-row');
    expect($html)->toContain('lg:h-16');
    expect(preg_match('/<div class="(?<classes>relative flex min-h-screen[^"]*)">/', $html, $wrapper))->toBe(1);
    expect($wrapper['classes'])->toContain('pt-4');
    expect($wrapper['classes'])->toContain('sm:pt-6');
    expect($wrapper['classes'])->not->toMatch('/(?:^|\s)(?:pt-(?:16|20|24|28|32|36|40|48)|md:pt-|lg:pt-|xl:pt-|2xl:pt-)/');
    expect($html)->not->toContain('Seleccionar contexto');
    expect($html)->not->toContain('data-panel-context-toolbar');
    expect($html)->not->toContain('selector-contexto-form');
    expect($html)->not->toContain('selector-contexto-tipo');
});

it('elimina el toolbar global y conserva la navegación lateral y el contexto de edición', function () {
    [$activa, $otra] = crearEdicionesHeaderEdicion();
    $admin = crearAdminHeaderEdicion();

    $html = $this->actingAs($admin)
        ->get('/Panel/admin?idEdicion='.$activa->id)
        ->assertSuccessful()
        ->getContent();

    $plantilla = file_get_contents(resource_path('views/Panel/admin.blade.php'));

    expect($plantilla)->not->toContain('Tarjeta de Estadísticas de Jugadores en Sidebar');
    expect($plantilla)->not->toContain('Jugadores Totales');
    expect($plantilla)->not->toContain('Por Categoría:');
    expect($html)->toContain('Estadísticas de Jugadores');
    expect($html)->toContain('href="'.route('equipo.index', ['idEdicion' => $activa->id]).'"');
    expect($html)->toContain('href="'.route('tabla_goleador.index', ['idEdicion' => $activa->id]).'"');
    expect($html)->toContain('href="'.route('partido.index', ['idEdicion' => $activa->id]).'"');
    expect($html)->not->toContain('Seleccionar contexto');
    expect($html)->not->toContain('data-panel-context-toolbar');
    expect($html)->not->toContain('selector-contexto-form');
    expect(file_get_contents(resource_path('js/admin/admin.js')))->not->toContain('selector-contexto-form');
});

it('el header del panel no aparece en el portal público', function () {
    Edicion::create(['nombre' => 'Edicion Publica']);

    $this->get('/')
        ->assertSuccessful()
        ->assertDontSee('data-panel-edicion-header', false);
});
