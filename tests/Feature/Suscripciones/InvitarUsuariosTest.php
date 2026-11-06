<?php

// Invitación única a suscribirse para los usuarios con cuenta que nunca entraron a la lista.
// Solo quedan suscriptos si confirman con el enlace (doble opt-in), y nadie recibe la invitación dos veces.

use App\Mail\ConfirmacionSuscripcionMail;
use App\Mail\InvitacionSuscripcionMail;
use App\Models\Suscriptor;
use App\Models\User;
use App\Services\SuscripcionService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/** @param  array<string, mixed>  $atributos */
function usuarioParaInvitar(string $email, array $atributos = []): User
{
    return User::factory()->create(['email' => $email, 'email_subscribed' => true, 'email_verified_at' => null, ...$atributos]);
}

/** Deja el suscriptor con la antigüedad indicada, como si se hubiera creado ese día. */
function crearSuscriptorConFecha(Suscriptor $suscriptor, string $creado, ?string $confirmadoAt = null): void
{
    DB::table('suscriptores')->where('id', $suscriptor->id)->update([
        'created_at' => $creado,
        'updated_at' => $creado,
        'confirmado_at' => $confirmadoAt,
    ]);
}

it('en modo simulación informa a cuántos invitaría y no guarda ni envía nada', function (): void {
    Mail::fake();
    usuarioParaInvitar('uno@correo.com');
    usuarioParaInvitar('dos@correo.com');

    $this->artisan('suscripciones:invitar-usuarios', ['--simular' => true])
        ->expectsOutputToContain('Usuarios para invitar: 2')
        ->expectsOutputToContain('Se invitarían: 2')
        ->expectsOutputToContain('Modo simulación: no se envió ningún correo.')
        ->assertSuccessful();

    Mail::assertNothingQueued();
    expect(Suscriptor::query()->count())->toBe(0);
});

it('invita sin pedir confirmación cuando se fuerza el lote programado y marca la invitación', function (): void {
    Mail::fake();
    $this->freezeTime();
    usuarioParaInvitar('uno@correo.com');

    $this->artisan('suscripciones:invitar-usuarios', ['--forzar' => true])
        ->expectsOutputToContain('Invitaciones en cola: 1')
        ->assertSuccessful();

    Mail::assertQueuedCount(1);
    expect(Suscriptor::query()->sole()->invitado_at?->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('sin candidatos no envía nada ni siquiera forzando el lote', function (): void {
    Mail::fake();

    $this->artisan('suscripciones:invitar-usuarios', ['--forzar' => true, '--limite' => 50])
        ->expectsOutputToContain('No hay usuarios para invitar.')
        ->assertSuccessful();

    Mail::assertNothingQueued();
    expect(Suscriptor::query()->count())->toBe(0);
});

it('la consulta de candidatos se puede reutilizar y devuelve un correo normalizado por usuario en orden de alta', function (): void {
    $primero = usuarioParaInvitar('Uno@Correo.com');
    usuarioParaInvitar(' uno@correo.com');
    usuarioParaInvitar('ya-esta@correo.com');
    Suscriptor::factory()->pendiente()->create(['email' => 'ya-esta@correo.com']);
    usuarioParaInvitar('sin-avisos@correo.com', ['email_subscribed' => false]);
    usuarioParaInvitar('bloqueado@mail.ru');
    $segundo = usuarioParaInvitar('dos@correo.com');

    $candidatos = app(SuscripcionService::class)->usuariosParaInvitar();

    expect(array_keys($candidatos))->toBe(['uno@correo.com', 'dos@correo.com'])
        ->and($candidatos['uno@correo.com']->id)->toBe($primero->id)
        ->and($candidatos['dos@correo.com']->id)->toBe($segundo->id);
});

it('marca como invitadas las invitaciones pendientes del 6 de noviembre al migrar', function (): void {
    expect(Schema::hasColumn('suscriptores', 'invitado_at'))->toBeTrue();
    $migracion = require database_path('migrations/2026_11_06_000001_add_invitado_at_to_suscriptores_table.php');
    $usuario = User::factory()->create(['email' => 'invitado@correo.com']);

    // El lote reenviado a mano el 2026-11-06 09:12 quedó pendiente y debe quedar marcado como invitación.
    $loteManual = Suscriptor::factory()->pendiente()->create(['email' => 'lote@correo.com', 'usuario_id' => $usuario->id]);
    crearSuscriptorConFecha($loteManual, '2026-11-06 09:12:00');
    $posterior = Suscriptor::factory()->pendiente()->create(['email' => 'posterior@correo.com', 'usuario_id' => $usuario->id]);
    crearSuscriptorConFecha($posterior, '2026-11-07 10:00:00');
    $anterior = Suscriptor::factory()->pendiente()->create(['email' => 'anterior@correo.com', 'usuario_id' => $usuario->id]);
    crearSuscriptorConFecha($anterior, '2026-11-05 23:59:59');
    $confirmada = Suscriptor::factory()->pendiente()->create(['email' => 'confirmada@correo.com', 'usuario_id' => $usuario->id]);
    crearSuscriptorConFecha($confirmada, '2026-11-06 10:00:00', confirmadoAt: '2026-11-06 11:00:00');
    $delSitio = Suscriptor::factory()->pendiente()->create(['email' => 'sitio@correo.com']);
    crearSuscriptorConFecha($delSitio, '2026-11-06 10:00:00');

    $migracion->marcarInvitacionesPrevias();

    expect(DB::table('suscriptores')->orderBy('id')->pluck('invitado_at', 'email')->all())->toBe([
        'lote@correo.com' => '2026-11-06 09:12:00',
        'posterior@correo.com' => '2026-11-07 10:00:00',
        'anterior@correo.com' => null,
        'confirmada@correo.com' => null,
        'sitio@correo.com' => null,
    ]);
});

it('invita a los usuarios fuera de la lista y los deja pendientes hasta que confirmen', function (): void {
    Mail::fake();
    $usuario = usuarioParaInvitar('  Madre.Halcones@Correo.com ');

    $this->artisan('suscripciones:invitar-usuarios')
        ->expectsConfirmation('¿Invitar a suscribirse a 1 usuario(s)?', 'yes')
        ->expectsOutputToContain('Invitaciones en cola: 1')
        ->assertSuccessful();

    $suscriptor = Suscriptor::query()->sole();
    expect($suscriptor->only(['email', 'usuario_id', 'recibe_resumen_fecha', 'recibe_avisos', 'recibe_proxima_fecha']))->toBe([
        'email' => 'madre.halcones@correo.com',
        'usuario_id' => $usuario->id,
        'recibe_resumen_fecha' => true,
        'recibe_avisos' => true,
        'recibe_proxima_fecha' => true,
    ])->and($suscriptor->estaActivo())->toBeFalse()
        ->and($suscriptor->invitado_at)->not->toBeNull();

    Mail::assertQueuedCount(1);
    Mail::assertQueued(InvitacionSuscripcionMail::class, fn (InvitacionSuscripcionMail $mail): bool => $mail->hasTo('madre.halcones@correo.com')
        && str_contains($mail->urlConfirmacion, '/'.$suscriptor->id));
    Mail::assertNotQueued(ConfirmacionSuscripcionMail::class);
});

it('no invita a quien ya está en la lista, apagó los correos o tiene un dominio bloqueado', function (): void {
    Mail::fake();
    usuarioParaInvitar('activo@correo.com');
    Suscriptor::factory()->activo()->create(['email' => 'activo@correo.com']);
    usuarioParaInvitar('baja@correo.com');
    Suscriptor::factory()->deBaja()->create(['email' => 'baja@correo.com']);
    usuarioParaInvitar('sin-correos@correo.com', ['email_subscribed' => false]);
    usuarioParaInvitar('bloqueado@mail.ru');
    usuarioParaInvitar('invitable@correo.com');

    $this->artisan('suscripciones:invitar-usuarios')
        ->expectsConfirmation('¿Invitar a suscribirse a 1 usuario(s)?', 'yes')
        ->expectsOutputToContain('Invitaciones en cola: 1')
        ->assertSuccessful();

    Mail::assertQueuedCount(1);
    Mail::assertQueued(InvitacionSuscripcionMail::class, fn (InvitacionSuscripcionMail $mail): bool => $mail->hasTo('invitable@correo.com'));
    expect(Suscriptor::query()->where('email', 'baja@correo.com')->sole()->baja_at)->not->toBeNull();
});

it('respeta el límite y la corrida siguiente sigue con los que faltan sin repetir', function (): void {
    Mail::fake();
    usuarioParaInvitar('a@correo.com');
    usuarioParaInvitar('b@correo.com');
    usuarioParaInvitar('c@correo.com');

    $this->artisan('suscripciones:invitar-usuarios', ['--limite' => 2])
        ->expectsConfirmation('¿Invitar a suscribirse a 2 usuario(s)?', 'yes')
        ->expectsOutputToContain('Invitaciones en cola: 2')
        ->expectsOutputToContain('Quedan 1 usuario(s) por invitar: volvé a correr el comando otro día.')
        ->assertSuccessful();

    $this->artisan('suscripciones:invitar-usuarios', ['--limite' => 2])
        ->expectsConfirmation('¿Invitar a suscribirse a 1 usuario(s)?', 'yes')
        ->expectsOutputToContain('Invitaciones en cola: 1')
        ->assertSuccessful();

    $this->artisan('suscripciones:invitar-usuarios')
        ->expectsOutputToContain('No hay usuarios para invitar.')
        ->assertSuccessful();

    Mail::assertQueuedCount(3);
    foreach (['a@correo.com', 'b@correo.com', 'c@correo.com'] as $email) {
        Mail::assertQueued(InvitacionSuscripcionMail::class, fn (InvitacionSuscripcionMail $mail): bool => $mail->hasTo($email));
    }
});

it('espacia las invitaciones un segundo para no superar el límite de pedidos por segundo de Resend', function (): void {
    $this->freezeTime();
    usuarioParaInvitar('a@correo.com');
    usuarioParaInvitar('b@correo.com');
    $servicio = $this->partialMock(SuscripcionService::class);
    $servicio->shouldReceive('invitar')->andReturn(true);

    $this->artisan('suscripciones:invitar-usuarios')
        ->expectsConfirmation('¿Invitar a suscribirse a 2 usuario(s)?', 'yes')
        ->assertSuccessful();

    $momentos = [];
    $servicio->shouldHaveReceived('invitar')->twice()->withArgs(function (User $usuario, string $email, CarbonInterface $enviarA) use (&$momentos): bool {
        $momentos[] = $enviarA->getTimestamp();

        return true;
    });
    expect($momentos)->toBe([now()->addSecond()->getTimestamp(), now()->addSeconds(2)->getTimestamp()]);
});

it('si se cancela no guarda ni envía nada', function (): void {
    Mail::fake();
    usuarioParaInvitar('uno@correo.com');

    $this->artisan('suscripciones:invitar-usuarios')
        ->expectsConfirmation('¿Invitar a suscribirse a 1 usuario(s)?', 'no')
        ->expectsOutputToContain('No se envió ningún correo.')
        ->assertSuccessful();

    Mail::assertNothingQueued();
    expect(Suscriptor::query()->count())->toBe(0);
});

it('al confirmar el enlace de la invitación queda suscripto', function (): void {
    Mail::fake();
    usuarioParaInvitar('uno@correo.com');

    $this->artisan('suscripciones:invitar-usuarios')
        ->expectsConfirmation('¿Invitar a suscribirse a 1 usuario(s)?', 'yes')
        ->assertSuccessful();

    $enlace = null;
    Mail::assertQueued(InvitacionSuscripcionMail::class, function (InvitacionSuscripcionMail $mail) use (&$enlace): bool {
        $enlace = $mail->urlConfirmacion;

        return true;
    });

    $this->get($enlace)->assertRedirect('/')->assertSessionHas('suscripcion', '¡Listo! Ya estás suscripto a los avisos de Torneos Mita\'í.');

    expect(Suscriptor::query()->sole()->estaActivo())->toBeTrue();
});

it('la invitación explica por qué llega y no dice que la persona pidió suscribirse', function (): void {
    $html = (new InvitacionSuscripcionMail('https://torneosmitai.com/confirmar'))->render();

    expect($html)->toContain('Tenés una cuenta en Torneos Mita')
        ->toContain('https://torneosmitai.com/confirmar')
        ->not->toContain('Pediste recibir');
});
