<?php

// Feature tests de la migración de Suscriptores a los componentes x-ui.*
// (T9a, panel-admin-redesign). Se asegura la paridad funcional con la vista
// vieja: ids, data-* y funciones JS del envío por lotes, el formulario de
// filtros, el reenvío de verificación por fila y el estado vacío; y las
// piezas nuevas: tarjetas, badges y el total de cuentas sin verificar.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Fecha;
use App\Models\User;

function crearAdminSuscriptores(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
        'name' => 'Admin Suscriptores',
        'email' => 'admin.suscriptores@example.com',
    ]);
}

it('renderiza el encabezado y las tres tarjetas', function () {
    $response = $this->actingAs(crearAdminSuscriptores())->get(route('notificaciones.index'));

    $response->assertOk()
        ->assertSee('Suscriptores')
        ->assertSee('Familias que reciben novedades del torneo por email.')
        ->assertSee('Resumen de la fecha')
        ->assertSee('Aviso rápido')
        ->assertSee('Recordatorio de verificación');
});

it('conserva todos los ids y funciones del envio por lotes', function () {
    $html = $this->actingAs(crearAdminSuscriptores())->get(route('notificaciones.index'))->getContent();

    foreach ([
        'id="recordatorios-container"',
        'id="btn-enviar-recordatorios"',
        'iniciarEnvioRecordatorios()',
        'id="btn-text"',
        'id="notif-dropdown-container"',
        'id="btn-manual"',
        'toggleNotifDropdown()',
        'id="notif-dropdown"',
        'prepararNotificacionDesdeBoton(this)',
        'abrirModalNotificacion()',
        'id="sw-titulo"',
        'id="sw-mensaje"',
        'id="sw-url"',
        'id="sw-icono"',
        'id="btn-detener"',
        'detenerEnvio()',
        'id="btn-continuar"',
        'continuarEnvioPausado()',
        'id="progreso-recordatorios"',
        'id="progreso-spinner"',
        'id="progreso-texto"',
        'id="error-debug-msg"',
        'id="resumen-fecha-controls"',
        'id="resumen-idCategoria"',
        'id="resumen-idFecha"',
        'id="btn-enviar-resumen-fecha"',
        'iniciarEnvioResumenFecha()',
        'actualizarContextoResumen',
        'filtrarFechasResumen',
        'ENDPOINTS',
        'procesarLotes',
        'pausarPorProveedor',
        'reanudarEnvio',
        'finalizarEnvio',
        'mostrarProgreso',
        'mitai_sending_state',
    ] as $esperado) {
        expect($html)->toContain($esperado);
    }
});

it('los botones de aviso rapido conservan sus data-notif', function () {
    $edicion = Edicion::create(['nombre' => 'Edicion Suscriptores']);

    $html = $this->actingAs(crearAdminSuscriptores())->get(route('notificaciones.index'))->getContent();

    preg_match_all('/<button\b[^>]*data-notif-titulo="([^"]*)"[^>]*>/s', $html, $botones);
    expect($botones[1])->toHaveCount(2);

    foreach (['data-notif-mensaje=', 'data-notif-url=', 'data-notif-icono='] as $atributo) {
        expect(substr_count($html, $atributo))->toBe(2);
    }
    expect($html)->toContain('data-notif-url="'.route('fixture', ['idEdicion' => $edicion->id]).'"');
});

it('las selects del resumen de fecha vienen de la edicion vigente', function () {
    $edicion = Edicion::create(['nombre' => 'Edicion Suscriptores Resumen']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 10 Suscriptores']);
    $fecha = Fecha::create(['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'nombre' => 'Fecha Suscriptores 3']);

    $html = $this->actingAs(crearAdminSuscriptores())->get(route('notificaciones.index'))->getContent();

    expect($html)
        ->toContain('<option value="'.$categoria->id.'"')
        ->toContain('Sub 10 Suscriptores')
        ->toContain('data-categoria="'.$categoria->id.'"')
        ->toContain('Fecha Suscriptores 3')
        ->toContain('Edicion Suscriptores Resumen');
    expect($fecha->id)->toBeInt();
});

it('el formulario de filtros conserva nombres y opciones', function () {
    $html = $this->actingAs(crearAdminSuscriptores())->get(route('notificaciones.index'))->getContent();

    expect($html)
        ->toContain('name="search"')
        ->toContain('name="verified_status"')
        ->toContain('name="email_notif_status"')
        ->toContain('Limpiar');

    foreach (['todos', 'verificado', 'no_verificado', 'activo', 'inactivo'] as $valor) {
        expect($html)->toContain('<option value="'.$valor.'"');
    }
});

it('los filtros siguen filtrando la tabla', function () {
    User::factory()->create(['name' => 'Verificada Familia', 'email' => 'verificada@example.com', 'email_verified_at' => now()]);
    User::factory()->unverified()->create(['name' => 'Pendiente Familia', 'email' => 'pendiente@example.com']);

    $response = $this->actingAs(crearAdminSuscriptores())
        ->get(route('notificaciones.index', ['verified_status' => 'no_verificado']));

    $response->assertOk()
        ->assertSee('Pendiente Familia')
        ->assertDontSee('Verificada Familia');
});

it('el reenvio de verificacion solo aparece para usuarios sin verificar', function () {
    $pendiente = User::factory()->unverified()->create(['name' => 'Pendiente Reenvio', 'email' => 'pendiente.reenvio@example.com']);
    $verificado = User::factory()->create(['name' => 'Verificado Reenvio', 'email' => 'verificado.reenvio@example.com', 'email_verified_at' => now()]);

    $html = $this->actingAs(crearAdminSuscriptores())->get(route('notificaciones.index'))->getContent();

    $accionPendiente = route('notificaciones.reenviar-verificacion', $pendiente->id);
    $accionVerificado = route('notificaciones.reenviar-verificacion', $verificado->id);

    expect($html)->toContain('action="'.$accionPendiente.'"')
        ->not->toContain('action="'.$accionVerificado.'"');

    preg_match('/<form[^>]*action="'.preg_quote($accionPendiente, '/').'".*?<\/form>/s', $html, $formulario);
    expect($formulario[0])->toContain('method="POST"')->toContain('name="_token"');
});

it('muestra los badges de verificacion y suscripcion', function () {
    User::factory()->create(['name' => 'Suscripta Badge', 'email' => 'suscripta@example.com', 'email_verified_at' => now(), 'email_subscribed' => true]);
    User::factory()->unverified()->create(['name' => 'De Baja Badge', 'email' => 'baja@example.com', 'email_subscribed' => false]);

    $response = $this->actingAs(crearAdminSuscriptores())->get(route('notificaciones.index'));

    $response->assertOk()
        ->assertSee('Verificado')
        ->assertSee('Pendiente')
        ->assertSee('Suscripto')
        ->assertSee('De baja');
});

it('la tarjeta de recordatorio muestra el total real de cuentas sin verificar', function () {
    User::factory()->unverified()->count(3)->create();

    $response = $this->actingAs(crearAdminSuscriptores())->get(route('notificaciones.index'));

    $response->assertOk()
        ->assertSee('3 cuentas sin verificar el email. Se envía por tandas para no superar el límite del servidor.');
});

it('muestra el estado vacio con direccion cuando los filtros no devuelven usuarios', function () {
    $response = $this->actingAs(crearAdminSuscriptores())
        ->get(route('notificaciones.index', ['search' => 'zzz-no-existe-zzz']));

    $response->assertOk()
        ->assertSee('No se encontraron usuarios con esos filtros')
        ->assertSee('Limpiar');
});

it('un invitado no puede ver suscriptores', function () {
    $this->get(route('notificaciones.index'))->assertRedirect('/login');
});

it('un usuario sin rol admin no puede ver suscriptores', function () {
    $usuario = User::factory()->create(['rol' => 'regular', 'email_verified_at' => now()]);

    $this->actingAs($usuario)->get(route('notificaciones.index'))->assertRedirect('/');
});
