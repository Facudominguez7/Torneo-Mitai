<?php

// Feature tests para Premios individuales (T8c, panel-admin-redesign):
// fusión de Goleador del torneo y Valla menos vencida. Cubre el listado
// (guardado con Cambiar/Eliminar, sugerencia con select+Guardar, "Cargar a
// mano" siempre disponible, y estados vacíos), premios.goleador (resuelve
// nombre/equipo en el servidor, nunca confía en el cliente, rechaza
// duplicados/dni inválido/ids inválidos), premios.valla (valida nombre,
// verifica el equipo contra EquipoEdicion, rechaza duplicados/equipo de otra
// categoría/ids inválidos), los redirects de goleador.index/valla.index y
// que el CRUD de goleador/valla siga funcionando (incluida la
// preselección del equipo en edición).

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Goleador;
use App\Models\Jugador;
use App\Models\TablaGoleador;
use App\Models\User;
use App\Models\VallaMenosVencida;
use Illuminate\Support\Facades\Log;

function crearAdminPremios(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

/**
 * @return array{edicion: Edicion, categoria: Categoria}
 */
function crearContextoPremios(array $atributos = []): array
{
    $edicion = Edicion::create(['nombre' => $atributos['nombreEdicion'] ?? 'Edicion Premios Feature']);
    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => $atributos['nombreCategoria'] ?? 'Sub 15 Premios Feature',
    ]);

    return compact('edicion', 'categoria');
}

function crearEquipoParaPremios(Edicion $edicion, Categoria $categoria, string $nombre, int $golesContra = 0): Equipo
{
    $equipo = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => $nombre,
        'foto' => 'escudo.png',
    ]);

    EquipoEdicion::create([
        'idEdicion' => $edicion->id,
        'idEquipo' => $equipo->id,
        'idCategoria' => $categoria->id,
        'golesContra' => $golesContra,
    ]);

    return $equipo;
}

function crearFilaTablaGoleadorParaPremios(array $contexto, Equipo $equipo, string $dni, string $nombre, string $apellido, int $goles): TablaGoleador
{
    Jugador::create([
        'dni' => $dni,
        'nombre' => $nombre,
        'apellido' => $apellido,
        'goles_totales' => $goles,
        'partidos_totales' => 1,
    ]);

    return TablaGoleador::create([
        'idEquipo' => $equipo->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'dni_jugador' => $dni,
        'cantidadGoles' => $goles,
    ]);
}

function tokenCsrfPremios(): array
{
    return ['_token' => 'token-csrf-premios'];
}

function headersCsrfPremios(): array
{
    return ['X-CSRF-TOKEN' => 'token-csrf-premios'];
}

// ---- Acceso -----------------------------------------------------------

it('un invitado no puede ver Premios', function () {
    $this->get(route('premios.index'))->assertRedirect('/login');
});

it('un usuario sin rol admin no puede ver Premios', function () {
    $usuario = User::factory()->create(['rol' => 'regular', 'email_verified_at' => now()]);

    $this->actingAs($usuario)->get(route('premios.index'))->assertRedirect('/');
});

it('un invitado no puede guardar goleador ni valla', function () {
    // Con token válido, para que el rechazo venga de auth y no de CSRF (419).
    $this->withSession(['_token' => 'token-invitado-premios'])
        ->post(route('premios.goleador'), ['_token' => 'token-invitado-premios'])
        ->assertRedirect('/login');
    $this->withSession(['_token' => 'token-invitado-premios'])
        ->post(route('premios.valla'), ['_token' => 'token-invitado-premios'])
        ->assertRedirect('/login');
    expect(Goleador::count())->toBe(0)
        ->and(VallaMenosVencida::count())->toBe(0);
});

// ---- Listado ------------------------------------------------------------

it('renderiza el encabezado con el subtitulo y la nota al pie', function () {
    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();

    $response = $this->actingAs($admin)->get(route('premios.index', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('Premios individuales')
        ->assertSee('Goleador del torneo y valla menos vencida de cada categoría. Elegí de la sugerencia o cargalo a mano.');
});

it('renderiza el estado vacio de seleccionar edicion cuando no hay edicion en contexto', function () {
    $admin = crearAdminPremios();

    $this->actingAs($admin)->get(route('premios.index'))
        ->assertOk()
        ->assertSee('Seleccioná una edición para ver los premios');
});

it('renderiza el estado vacio cuando la edicion no tiene nada para sugerir ni guardado', function () {
    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();

    $response = $this->actingAs($admin)->get(route('premios.index', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('Todavía no hay tabla de goleadores ni goles en contra cargados en esta edición');
});

it('muestra la sugerencia de goleador con el select preseleccionado y el formulario Guardar', function () {
    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();
    $equipo = crearEquipoParaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Sugerencia Goleador');
    crearFilaTablaGoleadorParaPremios($contexto, $equipo, '40500001', 'Mateo', 'Duarte', 7);

    $html = $this->actingAs($admin)->get(route('premios.index', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->getContent();

    preg_match('/<form[^>]*action="'.preg_quote(e(route('premios.goleador')), '/').'".*?<\/form>/s', $html, $form);
    $formHtml = $form[0] ?? '';

    expect($formHtml)->not->toBeEmpty()
        ->and($formHtml)->toContain('name="idEdicion" value="'.$contexto['edicion']->id.'"')
        ->and($formHtml)->toContain('name="idCategoria" value="'.$contexto['categoria']->id.'"')
        ->and($formHtml)->toContain('name="dni_jugador"')
        ->and($formHtml)->toMatch('/<option value="40500001"\s+selected\s*>\s*Sugerido: Duarte Mateo \(7 goles\)\s*<\/option>/')
        ->and($formHtml)->toContain('Guardar');
});

it('muestra la sugerencia de valla con el select, el input de arquero y el formulario Guardar', function () {
    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();
    $equipo = crearEquipoParaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Sugerencia Valla', 2);

    $html = $this->actingAs($admin)->get(route('premios.index', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->getContent();

    preg_match('/<form[^>]*action="'.preg_quote(e(route('premios.valla')), '/').'".*?<\/form>/s', $html, $form);
    $formHtml = $form[0] ?? '';

    expect($formHtml)->not->toBeEmpty()
        ->and($formHtml)->toContain('name="idEdicion" value="'.$contexto['edicion']->id.'"')
        ->and($formHtml)->toContain('name="idCategoria" value="'.$contexto['categoria']->id.'"')
        ->and($formHtml)->toContain('name="idEquipo"')
        ->and($formHtml)->toMatch('/<option value="'.$equipo->id.'"\s+selected\s*>\s*Sugerido: Equipo Sugerencia Valla \(2 en contra\)\s*<\/option>/')
        ->and($formHtml)->toContain('name="nombre"')
        ->and($formHtml)->toContain('Arquero')
        ->and($formHtml)->toContain('Guardar');
});

it('muestra el goleador y la valla guardados con Cambiar y Eliminar', function () {
    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();
    $equipoGoleador = crearEquipoParaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Goleador Guardado');
    $equipoValla = crearEquipoParaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Valla Guardada');

    $goleador = Goleador::create([
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idEquipo' => $equipoGoleador->id,
        'nombre' => 'Duarte Mateo Guardado',
    ]);
    $valla = VallaMenosVencida::create([
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idEquipo' => $equipoValla->id,
        'nombre' => 'Arquero Guardado',
    ]);

    $response = $this->actingAs($admin)->get(route('premios.index', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('Duarte Mateo Guardado')
        ->assertSee('Equipo Goleador Guardado')
        ->assertSee('Arquero Guardado')
        ->assertSee('Equipo Valla Guardada')
        ->assertSee(e(route('goleador.edit', $goleador)), false)
        ->assertSee(e(route('valla.edit', $valla)), false)
        ->assertSee(e(route('goleador.destroy', $goleador)), false)
        ->assertSee(e(route('valla.destroy', $valla)), false)
        ->assertSee('data-confirm-delete', false)
        ->assertSee('value="DELETE"', false)
        ->assertSee('Cambiar');
});

it('siempre ofrece Cargar a mano para goleador y valla, incluso con algo guardado', function () {
    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();
    $equipo = crearEquipoParaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Manual Premios');

    Goleador::create([
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idEquipo' => $equipo->id,
        'nombre' => 'Goleador Ya Cargado',
    ]);

    $response = $this->actingAs($admin)->get(route('premios.index', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee(e(route('seleccionar-categoria', [
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'tipo' => 'goleador',
        ])), false)
        ->assertSee(e(route('seleccionar-categoria', [
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'tipo' => 'valla',
        ])), false)
        ->assertSee('Cargar a mano');
});

it('sin sugerencia ni guardado avisa que todavia no hay sugerencia disponible', function () {
    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();
    // Solo hay valla sugerible (equipo cargado), pero ningún goleador en la
    // tabla de goleadores: la celda de goleador no tiene nada para sugerir.
    crearEquipoParaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Solo Valla Premios', 4);

    $response = $this->actingAs($admin)->get(route('premios.index', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()->assertSee('Sin sugerencia disponible todavía');
});

// ---- premios.goleador -----------------------------------------------------

it('premios.goleador crea exactamente un goleador con el nombre y equipo resueltos en el servidor', function () {
    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();
    $equipo = crearEquipoParaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Confirmar Goleador');
    crearFilaTablaGoleadorParaPremios($contexto, $equipo, '40600001', 'Lucas', 'Fernandez', 9);

    $response = $this->actingAs($admin)->withSession(tokenCsrfPremios())->withHeaders(headersCsrfPremios())->post(route('premios.goleador'), [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'dni_jugador' => '40600001',
    ]);

    $response->assertRedirect(route('premios.index', ['idEdicion' => $contexto['edicion']->id]));
    $response->assertSessionHas('status');

    expect(Goleador::where('idCategoria', $contexto['categoria']->id)->count())->toBe(1);
    $guardado = Goleador::where('idCategoria', $contexto['categoria']->id)->first();
    expect($guardado->nombre)->toBe('Fernandez Lucas')
        ->and($guardado->idEquipo)->toBe($equipo->id);
});

it('premios.goleador ignora el nombre y el equipo que mande el cliente', function () {
    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();
    $equipo = crearEquipoParaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Real Goleador');
    $intruso = crearEquipoParaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Intruso Goleador');
    crearFilaTablaGoleadorParaPremios($contexto, $equipo, '40600002', 'Nico', 'Gimenez', 4);

    $this->actingAs($admin)->withSession(tokenCsrfPremios())->withHeaders(headersCsrfPremios())->post(route('premios.goleador'), [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'dni_jugador' => '40600002',
        'nombre' => 'Nombre Falso Intruso',
        'idEquipo' => $intruso->id,
    ]);

    $guardado = Goleador::where('idCategoria', $contexto['categoria']->id)->first();

    expect($guardado->nombre)->toBe('Gimenez Nico')
        ->and($guardado->idEquipo)->toBe($equipo->id)
        ->and($guardado->nombre)->not->toBe('Nombre Falso Intruso');
});

it('premios.goleador rechaza si ya hay un goleador cargado en esa categoria', function () {
    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();
    $equipo = crearEquipoParaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Ya Goleador');
    crearFilaTablaGoleadorParaPremios($contexto, $equipo, '40600003', 'Ya', 'Cargado', 3);

    Goleador::create([
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idEquipo' => $equipo->id,
        'nombre' => 'Goleador Previo',
    ]);

    $response = $this->actingAs($admin)->withSession(tokenCsrfPremios())->withHeaders(headersCsrfPremios())->post(route('premios.goleador'), [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'dni_jugador' => '40600003',
    ]);

    $response->assertRedirect(route('premios.index', ['idEdicion' => $contexto['edicion']->id]));
    $response->assertSessionHas('error');
    expect(Goleador::where('idCategoria', $contexto['categoria']->id)->count())->toBe(1);
});

it('premios.goleador rechaza un dni que no esta en la tabla de goleadores de esa categoria', function () {
    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();

    $response = $this->actingAs($admin)->withSession(tokenCsrfPremios())->withHeaders(headersCsrfPremios())->post(route('premios.goleador'), [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'dni_jugador' => '99999999',
    ]);

    $response->assertRedirect(route('premios.index', ['idEdicion' => $contexto['edicion']->id]));
    // El mensaje específico distingue el rechazo limpio de un fallo atrapado.
    $response->assertSessionHas('error', 'El jugador elegido ya no está disponible en la tabla de goleadores.');
    expect(Goleador::count())->toBe(0);
});

it('premios.goleador rechaza ids inexistentes o invalidos', function () {
    $admin = crearAdminPremios();

    $this->actingAs($admin)->withSession(tokenCsrfPremios())->withHeaders(headersCsrfPremios())->post(route('premios.goleador'), [
        'idEdicion' => 999999,
        'idCategoria' => 999999,
        'dni_jugador' => '12345678',
    ])->assertSessionHasErrors(['idEdicion', 'idCategoria']);

    $this->actingAs($admin)->withSession(tokenCsrfPremios())->withHeaders(headersCsrfPremios())->post(route('premios.goleador'), [])
        ->assertSessionHasErrors(['idEdicion', 'idCategoria', 'dni_jugador']);
});

it('si falla el guardado del goleador revierte todo, avisa y loguea la excepcion', function () {
    Log::spy();

    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();
    $equipo = crearEquipoParaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Fallo Goleador');
    crearFilaTablaGoleadorParaPremios($contexto, $equipo, '40600004', 'Falla', 'Simulada', 6);

    Goleador::creating(function (): void {
        throw new RuntimeException('Fallo simulado al guardar el goleador');
    });

    $response = $this->actingAs($admin)->withSession(tokenCsrfPremios())->withHeaders(headersCsrfPremios())->post(route('premios.goleador'), [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'dni_jugador' => '40600004',
    ]);

    $response->assertRedirect(route('premios.index', ['idEdicion' => $contexto['edicion']->id]));
    $response->assertSessionHas('error', 'No se pudo guardar el goleador. Intentá nuevamente.');
    expect(Goleador::count())->toBe(0);

    Log::shouldHaveReceived('error')->once()->with(
        'No se pudo guardar el goleador sugerido',
        Mockery::on(function (array $contexto) {
            return array_key_exists('exception', $contexto)
                && $contexto['exception'] instanceof RuntimeException
                && $contexto['exception']->getMessage() === 'Fallo simulado al guardar el goleador';
        })
    );
});

// ---- premios.valla --------------------------------------------------------

it('premios.valla crea exactamente una valla con el nombre del arquero cargado', function () {
    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();
    $equipo = crearEquipoParaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Confirmar Valla', 1);

    $response = $this->actingAs($admin)->withSession(tokenCsrfPremios())->withHeaders(headersCsrfPremios())->post(route('premios.valla'), [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idEquipo' => $equipo->id,
        'nombre' => 'Arquero Cargado a Mano',
    ]);

    $response->assertRedirect(route('premios.index', ['idEdicion' => $contexto['edicion']->id]));
    $response->assertSessionHas('status');

    expect(VallaMenosVencida::where('idCategoria', $contexto['categoria']->id)->count())->toBe(1);
    $guardado = VallaMenosVencida::where('idCategoria', $contexto['categoria']->id)->first();
    expect($guardado->nombre)->toBe('Arquero Cargado a Mano')
        ->and($guardado->idEquipo)->toBe($equipo->id);
});

it('premios.valla valida que el nombre del arquero sea obligatorio', function () {
    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();
    $equipo = crearEquipoParaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Sin Nombre Arquero', 1);

    $this->actingAs($admin)->withSession(tokenCsrfPremios())->withHeaders(headersCsrfPremios())->post(route('premios.valla'), [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idEquipo' => $equipo->id,
        'nombre' => '',
    ])->assertSessionHasErrors(['nombre']);

    expect(VallaMenosVencida::count())->toBe(0);
});

it('premios.valla rechaza un equipo de otra categoria', function () {
    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();
    $otraCategoria = Categoria::create(['idEdicion' => $contexto['edicion']->id, 'nombreCategoria' => 'Otra Categoria Valla']);
    $equipoOtraCategoria = crearEquipoParaPremios($contexto['edicion'], $otraCategoria, 'Equipo De Otra Categoria', 0);

    $response = $this->actingAs($admin)->withSession(tokenCsrfPremios())->withHeaders(headersCsrfPremios())->post(route('premios.valla'), [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idEquipo' => $equipoOtraCategoria->id,
        'nombre' => 'Arquero Intruso',
    ]);

    $response->assertRedirect(route('premios.index', ['idEdicion' => $contexto['edicion']->id]));
    $response->assertSessionHas('error');
    expect(VallaMenosVencida::count())->toBe(0);
});

it('premios.valla rechaza si ya hay una valla cargada en esa categoria', function () {
    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();
    $equipo = crearEquipoParaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Ya Valla', 2);

    VallaMenosVencida::create([
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idEquipo' => $equipo->id,
        'nombre' => 'Arquero Previo',
    ]);

    $response = $this->actingAs($admin)->withSession(tokenCsrfPremios())->withHeaders(headersCsrfPremios())->post(route('premios.valla'), [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idEquipo' => $equipo->id,
        'nombre' => 'Otro Arquero',
    ]);

    $response->assertRedirect(route('premios.index', ['idEdicion' => $contexto['edicion']->id]));
    $response->assertSessionHas('error');
    expect(VallaMenosVencida::where('idCategoria', $contexto['categoria']->id)->count())->toBe(1);
});

it('premios.valla rechaza ids inexistentes o invalidos', function () {
    $admin = crearAdminPremios();

    $this->actingAs($admin)->withSession(tokenCsrfPremios())->withHeaders(headersCsrfPremios())->post(route('premios.valla'), [
        'idEdicion' => 999999,
        'idCategoria' => 999999,
        'idEquipo' => 999999,
        'nombre' => 'Arquero Invalido',
    ])->assertSessionHasErrors(['idEdicion', 'idCategoria', 'idEquipo']);

    $this->actingAs($admin)->withSession(tokenCsrfPremios())->withHeaders(headersCsrfPremios())->post(route('premios.valla'), [])
        ->assertSessionHasErrors(['idEdicion', 'idCategoria', 'idEquipo', 'nombre']);
});

it('si falla el guardado de la valla revierte todo, avisa y loguea la excepcion', function () {
    Log::spy();

    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();
    $equipo = crearEquipoParaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Fallo Valla', 1);

    VallaMenosVencida::creating(function (): void {
        throw new RuntimeException('Fallo simulado al guardar la valla');
    });

    $response = $this->actingAs($admin)->withSession(tokenCsrfPremios())->withHeaders(headersCsrfPremios())->post(route('premios.valla'), [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idEquipo' => $equipo->id,
        'nombre' => 'Arquero Fallo',
    ]);

    $response->assertRedirect(route('premios.index', ['idEdicion' => $contexto['edicion']->id]));
    $response->assertSessionHas('error', 'No se pudo guardar la valla. Intentá nuevamente.');
    expect(VallaMenosVencida::count())->toBe(0);

    Log::shouldHaveReceived('error')->once()->with(
        'No se pudo guardar la valla sugerida',
        Mockery::on(function (array $contexto) {
            return array_key_exists('exception', $contexto)
                && $contexto['exception'] instanceof RuntimeException
                && $contexto['exception']->getMessage() === 'Fallo simulado al guardar la valla';
        })
    );
});

// ---- Redirects de goleador.index / valla.index ---------------------------

it('goleador.index redirige a Premios conservando idEdicion', function () {
    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();

    $this->actingAs($admin)->get(route('goleador.index', ['idEdicion' => $contexto['edicion']->id]))
        ->assertRedirect(route('premios.index', ['idEdicion' => $contexto['edicion']->id]));
});

it('valla.index redirige a Premios conservando idEdicion', function () {
    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();

    $this->actingAs($admin)->get(route('valla.index', ['idEdicion' => $contexto['edicion']->id]))
        ->assertRedirect(route('premios.index', ['idEdicion' => $contexto['edicion']->id]));
});

it('goleador.index sin idEdicion redirige a Premios sin parametros', function () {
    $admin = crearAdminPremios();

    $this->actingAs($admin)->get(route('goleador.index'))
        ->assertRedirect(route('premios.index'));
});

// ---- CRUD de Goleador / Valla (paridad) -----------------------------------

it('el alta de goleador usa x-ui.form-card, guarda y termina en Premios', function () {
    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();
    $equipo = crearEquipoParaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Alta Goleador Feature');

    $urlCreate = route('goleador.create', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]);

    $this->actingAs($admin)->get($urlCreate)
        ->assertOk()
        ->assertSee('Guardar goleador')
        ->assertSee('id="idEquipo"', false);

    $response = $this->actingAs($admin)->withSession(tokenCsrfPremios())->withHeaders(headersCsrfPremios())->post(route('goleador.store'), [
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $equipo->id,
        'nombre' => 'Goleador Manual Feature',
        'idCategoria' => $contexto['categoria']->id,
    ]);

    $response->assertRedirect(route('goleador.index', ['idEdicion' => $contexto['edicion']->id]));
    expect(Goleador::where('nombre', 'Goleador Manual Feature')->exists())->toBeTrue();

    $this->actingAs($admin)->get($response->headers->get('Location'))
        ->assertRedirect(route('premios.index', ['idEdicion' => $contexto['edicion']->id]));
});

it('la edicion de goleador usa Guardar cambios, actualiza y preselecciona el equipo actual con su nombre', function () {
    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();
    crearEquipoParaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Previo Goleador');
    $equipo = crearEquipoParaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Actual Goleador');
    $goleador = Goleador::create([
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idEquipo' => $equipo->id,
        'nombre' => 'Goleador Editar Feature',
    ]);

    $html = $this->actingAs($admin)->get(route('goleador.edit', $goleador))
        ->assertOk()
        ->assertSee('Guardar cambios')
        ->assertSee('action="'.route('goleador.update', $goleador->id).'"', false)
        ->getContent();

    preg_match('/<select[^>]*name="idEquipo"[^>]*>(.*?)<\/select>/s', $html, $selectEquipo);

    expect($selectEquipo[1] ?? '')
        ->toMatch('/<option value="'.$equipo->id.'"\s+selected\s*>\s*Equipo Actual Goleador\s*<\/option>/')
        ->not->toMatch('/<option value=""/');

    $otroEquipo = crearEquipoParaPremios($contexto['edicion'], $contexto['categoria'], 'Otro Equipo Goleador Feature');

    $response = $this->actingAs($admin)->withSession(tokenCsrfPremios())->withHeaders(headersCsrfPremios())->put(route('goleador.update', $goleador->id), [
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $otroEquipo->id,
        'nombre' => 'Goleador Actualizado Feature',
        'idCategoria' => $contexto['categoria']->id,
    ]);

    $response->assertRedirect(route('goleador.index', ['idEdicion' => $contexto['edicion']->id]));
    expect($goleador->refresh()->idEquipo)->toBe($otroEquipo->id)
        ->and($goleador->nombre)->toBe('Goleador Actualizado Feature');
});

it('destroy de goleador elimina el registro y termina en Premios', function () {
    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();
    $equipo = crearEquipoParaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Borrar Goleador Feature');
    $goleador = Goleador::create([
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idEquipo' => $equipo->id,
        'nombre' => 'Goleador Borrar Feature',
    ]);

    $response = $this->actingAs($admin)->withSession(tokenCsrfPremios())->withHeaders(headersCsrfPremios())->delete(route('goleador.destroy', $goleador), [
        'idEdicion' => $contexto['edicion']->id,
    ]);

    $response->assertRedirect(route('goleador.index', ['idEdicion' => $contexto['edicion']->id]));
    expect(Goleador::find($goleador->id))->toBeNull();
});

it('el alta de valla usa x-ui.form-card, guarda y termina en Premios', function () {
    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();
    $equipo = crearEquipoParaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Alta Valla Feature');

    $urlCreate = route('valla.create', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]);

    $this->actingAs($admin)->get($urlCreate)
        ->assertOk()
        ->assertSee('Guardar valla')
        ->assertSee('id="idEquipo"', false);

    $response = $this->actingAs($admin)->withSession(tokenCsrfPremios())->withHeaders(headersCsrfPremios())->post(route('valla.store'), [
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $equipo->id,
        'nombre' => 'Arquero Manual Feature',
        'idCategoria' => $contexto['categoria']->id,
    ]);

    $response->assertRedirect(route('valla.index', ['idEdicion' => $contexto['edicion']->id]));
    expect(VallaMenosVencida::where('nombre', 'Arquero Manual Feature')->exists())->toBeTrue();

    $this->actingAs($admin)->get($response->headers->get('Location'))
        ->assertRedirect(route('premios.index', ['idEdicion' => $contexto['edicion']->id]));
});

it('la edicion de valla usa Guardar cambios, actualiza y preselecciona el equipo actual con su nombre', function () {
    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();
    crearEquipoParaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Previo Valla');
    $equipo = crearEquipoParaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Actual Valla');
    $valla = VallaMenosVencida::create([
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idEquipo' => $equipo->id,
        'nombre' => 'Arquero Editar Feature',
    ]);

    $html = $this->actingAs($admin)->get(route('valla.edit', $valla))
        ->assertOk()
        ->assertSee('Guardar cambios')
        ->assertSee('action="'.route('valla.update', $valla->id).'"', false)
        ->getContent();

    preg_match('/<select[^>]*name="idEquipo"[^>]*>(.*?)<\/select>/s', $html, $selectEquipo);

    expect($selectEquipo[1] ?? '')
        ->toMatch('/<option value="'.$equipo->id.'"\s+selected\s*>\s*Equipo Actual Valla\s*<\/option>/')
        ->not->toMatch('/<option value=""/');

    $otroEquipo = crearEquipoParaPremios($contexto['edicion'], $contexto['categoria'], 'Otro Equipo Valla Feature');

    $response = $this->actingAs($admin)->withSession(tokenCsrfPremios())->withHeaders(headersCsrfPremios())->put(route('valla.update', $valla->id), [
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $otroEquipo->id,
        'nombre' => 'Arquero Actualizado Feature',
        'idCategoria' => $contexto['categoria']->id,
    ]);

    $response->assertRedirect(route('valla.index', ['idEdicion' => $contexto['edicion']->id]));
    expect($valla->refresh()->idEquipo)->toBe($otroEquipo->id)
        ->and($valla->nombre)->toBe('Arquero Actualizado Feature');
});

it('destroy de valla elimina el registro y termina en Premios', function () {
    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();
    $equipo = crearEquipoParaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Borrar Valla Feature');
    $valla = VallaMenosVencida::create([
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idEquipo' => $equipo->id,
        'nombre' => 'Arquero Borrar Feature',
    ]);

    $response = $this->actingAs($admin)->withSession(tokenCsrfPremios())->withHeaders(headersCsrfPremios())->delete(route('valla.destroy', $valla), [
        'idEdicion' => $contexto['edicion']->id,
    ]);

    $response->assertRedirect(route('valla.index', ['idEdicion' => $contexto['edicion']->id]));
    expect(VallaMenosVencida::find($valla->id))->toBeNull();
});

// ---- Navegación -----------------------------------------------------------

it('el sidebar muestra Premios individuales y no Goleadores del torneo suelto', function () {
    $admin = crearAdminPremios();
    $contexto = crearContextoPremios();

    $response = $this->actingAs($admin)->get(route('premios.index', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('>Premios individuales<', false)
        ->assertSee(e(route('premios.index', ['idEdicion' => $contexto['edicion']->id])), false)
        ->assertDontSee('Goleadores del torneo')
        ->assertSee(e(route('vallas.index', ['idEdicion' => $contexto['edicion']->id])), false);
});
