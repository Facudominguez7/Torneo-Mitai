<?php

// Retiro de los envíos masivos heredados (T10, S17): el aviso rápido por BCC,
// el recordatorio de verificación por tandas y los envíos de resultado ya no
// existen. Se conservan el resumen de la fecha, el reenvío de verificación por
// usuario y las bajas heredadas a las que apuntan los correos ya enviados.

use App\Models\User;
use App\Notifications\VerifyEmailCodeNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

function crearAdminRetiroEnvios(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
        'name' => 'Admin Retiro',
        'email' => 'admin.retiro@example.com',
    ]);
}

it('ya no registra la ruta retirada :dataset', function (string $nombre, string $metodo, string $uri) {
    expect(Route::has($nombre))->toBeFalse();

    $respuesta = $this->actingAs(crearAdminRetiroEnvios())
        ->withSession(['_token' => 'token-retiro'])
        ->json($metodo, $uri, [], ['X-CSRF-TOKEN' => 'token-retiro']);

    expect($respuesta->status())->toBeIn([404, 405]);
})->with([
    'contar recordatorios' => ['notificaciones.contar-pendientes', 'GET', '/Panel/notificaciones/recordatorios/contar'],
    'lote de recordatorios' => ['notificaciones.enviar-lote', 'POST', '/Panel/notificaciones/recordatorios/enviar-lote'],
    'log de envío completo' => ['notificaciones.log-completo', 'POST', '/Panel/notificaciones/recordatorios/log-completo'],
    'contar aviso rápido' => ['notificaciones.manual.contar', 'GET', '/Panel/notificaciones/manual/contar'],
    'lote de aviso rápido' => ['notificaciones.manual.enviar-lote', 'POST', '/Panel/notificaciones/manual/enviar-lote'],
    'lote de resultado' => ['notificaciones.resultado.enviar-lote', 'POST', '/Panel/notificaciones/resultado/enviar-lote'],
    'lote de resultado de instancia' => ['notificaciones.resultado-instancia.enviar-lote', 'POST', '/Panel/notificaciones/resultado-instancia/enviar-lote'],
]);

it('ya no registra el comando retirado :dataset', function (string $comando) {
    expect(array_key_exists($comando, Artisan::all()))->toBeFalse();
})->with([
    'recordatorio de verificación' => ['email:recordatorio-verificacion'],
    'notificar resultado' => ['mitai:notificar-resultado'],
]);

it('el panel muestra el resumen de la fecha y el reenvio por usuario pero no las tarjetas retiradas', function () {
    $pendiente = User::factory()->unverified()->create(['name' => 'Pendiente Retiro', 'email' => 'pendiente.retiro@example.com']);

    $html = $this->actingAs(crearAdminRetiroEnvios())
        ->get(route('notificaciones.index'))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('Resumen de la fecha')
        ->toContain('id="btn-enviar-resumen-fecha"')
        ->toContain('iniciarEnvioResumenFecha()')
        ->toContain('action="'.route('notificaciones.reenviar-verificacion', $pendiente->id).'"')
        ->toContain('Reenviar verificación');

    foreach ([
        'Aviso rápido',
        'Recordatorio de verificación',
        'id="btn-manual"',
        'data-notif-titulo',
        'id="btn-enviar-recordatorios"',
        'id="progreso-recordatorios"',
        'abrirModalNotificacion',
        'procesarLotes',
        'mitai_sending_state',
        '/Panel/notificaciones/recordatorios',
        '/Panel/notificaciones/manual',
    ] as $retirado) {
        expect($html)->not->toContain($retirado);
    }
});

it('el reenvio de verificacion sigue enviando el codigo a un usuario sin verificar', function () {
    Notification::fake();
    $pendiente = User::factory()->unverified()->create(['email' => 'pendiente.codigo@torneomitai.test']);

    $this->actingAs(crearAdminRetiroEnvios())
        ->withSession(['_token' => 'token-retiro'])
        ->from(route('notificaciones.index'))
        ->post(route('notificaciones.reenviar-verificacion', $pendiente->id), ['_token' => 'token-retiro'])
        ->assertRedirect(route('notificaciones.index'))
        ->assertSessionHas('success', 'Email de verificación enviado exitosamente a pendiente.codigo@torneomitai.test.');

    Notification::assertSentTo($pendiente, VerifyEmailCodeNotification::class);
    Notification::assertCount(1);
    expect($pendiente->fresh()->email_verified_at)->toBeNull();
});

it('el formulario de baja general heredado sigue respondiendo', function () {
    $this->get(route('email.baja-general.form'))
        ->assertOk()
        ->assertSee('name="email"', false);
});

it('la baja en un clic heredada sigue dando de baja al usuario', function () {
    $usuario = User::factory()->create(['email' => 'baja.clic@example.com', 'email_subscribed' => true]);

    $respuesta = $this->post(route('email.baja.post', ['id' => $usuario->id, 'token' => sha1($usuario->email)]));

    $respuesta->assertOk();
    expect($respuesta->getContent())->toBe('OK')
        ->and($usuario->fresh()->email_subscribed)->toBeFalse();
});
