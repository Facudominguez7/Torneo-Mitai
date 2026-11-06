<?php

// Reenvío del correo de confirmación a quienes pidieron suscribirse y nunca confirmaron
// (por ejemplo, porque Resend rechazó el envío original).

use App\Mail\ConfirmacionSuscripcionMail;
use App\Models\Suscriptor;
use App\Models\User;
use App\Services\SuscripcionService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Mail;

it('en modo simulación informa los pendientes y no envía nada', function (): void {
    Mail::fake();
    Suscriptor::factory()->pendiente()->count(2)->create();
    Suscriptor::factory()->activo()->create();

    $this->artisan('suscripciones:reenviar-confirmaciones', ['--simular' => true])
        ->expectsOutputToContain('Pendientes de confirmar: 2')
        ->expectsOutputToContain('Se reenviarían: 2')
        ->expectsOutputToContain('Modo simulación: no se envió ningún correo.')
        ->assertSuccessful();

    Mail::assertNothingQueued();
});

it('reenvía la confirmación solo a quienes nunca confirmaron ni se dieron de baja', function (): void {
    Mail::fake();
    $pendiente = Suscriptor::factory()->pendiente()->create(['email' => 'pendiente@correo.com']);
    Suscriptor::factory()->activo()->create(['email' => 'activo@correo.com']);
    Suscriptor::factory()->deBaja()->create(['email' => 'baja@correo.com']);
    Suscriptor::factory()->create(['email' => 'baja-sin-confirmar@correo.com', 'baja_at' => now()]);

    $this->artisan('suscripciones:reenviar-confirmaciones')
        ->expectsConfirmation('¿Reenviar el correo de confirmación a 1 suscriptor(es)?', 'yes')
        ->expectsOutputToContain('Quedaron en cola: 1')
        ->assertSuccessful();

    Mail::assertQueuedCount(1);
    Mail::assertQueued(ConfirmacionSuscripcionMail::class, function (ConfirmacionSuscripcionMail $mail) use ($pendiente): bool {
        return $mail->hasTo('pendiente@correo.com')
            && str_contains($mail->urlConfirmacion, '/'.$pendiente->id);
    });
    expect($pendiente->fresh()->confirmado_at)->toBeNull();
});

it('no le reenvía la confirmación a los usuarios invitados, que nunca pidieron suscribirse', function (): void {
    Mail::fake();
    $usuario = User::factory()->create(['email' => 'invitado@correo.com']);
    Suscriptor::factory()->pendiente()->create(['email' => 'invitado@correo.com', 'usuario_id' => $usuario->id]);

    $this->artisan('suscripciones:reenviar-confirmaciones')
        ->expectsOutputToContain('No hay suscriptores pendientes de confirmar.')
        ->assertSuccessful();

    Mail::assertNothingQueued();
});

it('respeta el límite y avisa desde qué ID seguir', function (): void {
    Mail::fake();
    $suscriptores = Suscriptor::factory()->pendiente()->count(3)->create();

    $this->artisan('suscripciones:reenviar-confirmaciones', ['--limite' => 2])
        ->expectsConfirmation('¿Reenviar el correo de confirmación a 2 suscriptor(es)?', 'yes')
        ->expectsOutputToContain('Quedaron en cola: 2')
        ->expectsOutputToContain('Quedan 1 pendiente(s). Para seguir: php artisan suscripciones:reenviar-confirmaciones --desde-id='.$suscriptores[1]->id)
        ->assertSuccessful();

    Mail::assertQueuedCount(2);
    Mail::assertNotQueued(ConfirmacionSuscripcionMail::class, fn (ConfirmacionSuscripcionMail $mail): bool => $mail->hasTo($suscriptores[2]->email));
});

it('con --desde-id sigue después del último enviado sin repetir', function (): void {
    Mail::fake();
    $suscriptores = Suscriptor::factory()->pendiente()->count(3)->create();

    $this->artisan('suscripciones:reenviar-confirmaciones', ['--desde-id' => $suscriptores[1]->id])
        ->expectsConfirmation('¿Reenviar el correo de confirmación a 1 suscriptor(es)?', 'yes')
        ->expectsOutputToContain('Quedaron en cola: 1')
        ->assertSuccessful();

    Mail::assertQueuedCount(1);
    Mail::assertQueued(ConfirmacionSuscripcionMail::class, fn (ConfirmacionSuscripcionMail $mail): bool => $mail->hasTo($suscriptores[2]->email));
});

it('espacia los envíos un segundo para no superar el límite de pedidos por segundo de Resend', function (): void {
    $this->freezeTime();
    Suscriptor::factory()->pendiente()->count(2)->create();
    $servicio = $this->spy(SuscripcionService::class);

    $this->artisan('suscripciones:reenviar-confirmaciones')
        ->expectsConfirmation('¿Reenviar el correo de confirmación a 2 suscriptor(es)?', 'yes')
        ->assertSuccessful();

    $momentos = [];
    $servicio->shouldHaveReceived('reenviarConfirmacion')->twice()->withArgs(function (Suscriptor $suscriptor, CarbonInterface $enviarA) use (&$momentos): bool {
        $momentos[] = $enviarA->getTimestamp();

        return true;
    });
    expect($momentos)->toBe([now()->addSecond()->getTimestamp(), now()->addSeconds(2)->getTimestamp()]);
});

it('si se cancela la confirmación no envía nada', function (): void {
    Mail::fake();
    Suscriptor::factory()->pendiente()->create();

    $this->artisan('suscripciones:reenviar-confirmaciones')
        ->expectsConfirmation('¿Reenviar el correo de confirmación a 1 suscriptor(es)?', 'no')
        ->expectsOutputToContain('No se envió ningún correo.')
        ->assertSuccessful();

    Mail::assertNothingQueued();
});

it('si no hay pendientes lo informa y no envía nada', function (): void {
    Mail::fake();
    Suscriptor::factory()->activo()->create();

    $this->artisan('suscripciones:reenviar-confirmaciones')
        ->expectsOutputToContain('No hay suscriptores pendientes de confirmar.')
        ->assertSuccessful();

    Mail::assertNothingQueued();
});
