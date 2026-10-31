<?php

// Suscripción con solo un email: doble opt-in, avisos elegidos y baja con enlace firmado.

use App\Mail\ConfirmacionSuscripcionMail;
use App\Models\Suscriptor;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    // El .env local puede apuntar a MailHog; los tests fijan el mailer de producción.
    config(['suscripciones.mailer' => 'resend', 'suscripciones.resend.sincronizar' => false]);
    Mail::fake();
});

/** @param  array<string, mixed>  $datos */
function suscribirse(array $datos): TestResponse
{
    return test()->withSession(['_token' => 'token-suscripcion'])
        ->from('/')
        ->post(route('suscripcion.store'), ['_token' => 'token-suscripcion', ...$datos]);
}

it('guarda la suscripcion pendiente y manda el correo de confirmacion', function (): void {
    suscribirse(['email' => '  Madre.Huracan@Correo.com '])
        ->assertRedirect('/')
        ->assertSessionHas('suscripcion', 'Te enviamos un correo a madre.huracan@correo.com. Abrilo y confirmá la suscripción.');

    $suscriptor = Suscriptor::query()->sole();
    expect($suscriptor->email)->toBe('madre.huracan@correo.com')
        ->and($suscriptor->confirmado_at)->toBeNull()
        ->and($suscriptor->estaActivo())->toBeFalse()
        ->and($suscriptor->recibe_resumen_fecha)->toBeTrue()
        ->and($suscriptor->recibe_avisos)->toBeTrue()
        ->and($suscriptor->recibe_proxima_fecha)->toBeTrue();

    Mail::assertQueued(ConfirmacionSuscripcionMail::class, function (ConfirmacionSuscripcionMail $mail) use ($suscriptor): bool {
        return $mail->hasTo('madre.huracan@correo.com')
            && $mail->mailer === 'resend'
            && str_contains($mail->urlConfirmacion, route('suscripcion.confirmar', $suscriptor, false))
            && str_contains($mail->urlConfirmacion, 'signature=');
    });
});

it('guarda los avisos que eligio', function (): void {
    suscribirse(['email' => 'abuelo@correo.com', 'avisos' => ['resumen_fecha']]);

    $suscriptor = Suscriptor::query()->sole();
    expect($suscriptor->recibe_resumen_fecha)->toBeTrue()
        ->and($suscriptor->recibe_avisos)->toBeFalse()
        ->and($suscriptor->recibe_proxima_fecha)->toBeFalse();
});

it('confirma la suscripcion con el enlace firmado', function (): void {
    $suscriptor = Suscriptor::factory()->pendiente()->create();

    $this->get(URL::temporarySignedRoute('suscripcion.confirmar', now()->addDay(), $suscriptor))
        ->assertRedirect('/')
        ->assertSessionHas('suscripcion', '¡Listo! Ya estás suscripto a los avisos de Torneos Mita\'í.');

    expect($suscriptor->fresh()->estaActivo())->toBeTrue();
});

it('rechaza un enlace de confirmacion sin firma o vencido', function (): void {
    $suscriptor = Suscriptor::factory()->pendiente()->create();
    $vencido = URL::temporarySignedRoute('suscripcion.confirmar', now()->addDay(), $suscriptor);

    $this->get(route('suscripcion.confirmar', $suscriptor))->assertForbidden();
    $this->travel(2)->days();
    $this->get($vencido)->assertForbidden();

    expect($suscriptor->fresh()->confirmado_at)->toBeNull();
});

it('da de baja con el enlace firmado aunque llegue sin token csrf', function (): void {
    app()['env'] = 'local';
    $suscriptor = Suscriptor::factory()->activo()->create();

    $this->post(URL::signedRoute('suscripcion.baja', $suscriptor))
        ->assertRedirect('/')
        ->assertSessionHas('suscripcion', 'Te diste de baja. No vas a recibir más avisos por correo.');

    expect($suscriptor->fresh()->baja_at)->not->toBeNull()
        ->and($suscriptor->fresh()->estaActivo())->toBeFalse();
});

it('no da de baja sin la firma del enlace', function (): void {
    $suscriptor = Suscriptor::factory()->activo()->create();

    $this->post(route('suscripcion.baja', $suscriptor))->assertForbidden();

    expect($suscriptor->fresh()->estaActivo())->toBeTrue();
});

it('no cambia las preferencias de un suscriptor activo desde el formulario publico', function (): void {
    $suscriptor = Suscriptor::factory()->activo()->create(['email' => 'padre@correo.com']);

    suscribirse(['email' => 'padre@correo.com', 'avisos' => ['resumen_fecha']])
        ->assertSessionHas('suscripcion', 'Te enviamos un correo a padre@correo.com. Abrilo y confirmá la suscripción.');

    $suscriptor->refresh();
    expect($suscriptor->estaActivo())->toBeTrue()
        ->and($suscriptor->recibe_avisos)->toBeTrue()
        ->and(Suscriptor::query()->count())->toBe(1);
    Mail::assertNothingOutgoing();
});

it('vuelve a pedir confirmacion a quien se habia dado de baja y la baja sigue hasta que confirme', function (): void {
    $suscriptor = Suscriptor::factory()->deBaja()->create(['email' => 'tia@correo.com']);
    $this->travel(1)->minute();

    suscribirse(['email' => 'tia@correo.com']);

    expect($suscriptor->fresh()->baja_at)->not->toBeNull();
    $enlace = null;
    Mail::assertQueued(ConfirmacionSuscripcionMail::class, function (ConfirmacionSuscripcionMail $mail) use (&$enlace): bool {
        $enlace = $mail->urlConfirmacion;

        return $mail->hasTo('tia@correo.com');
    });

    $this->get($enlace)->assertSessionHas('suscripcion', '¡Listo! Ya estás suscripto a los avisos de Torneos Mita\'í.');
    expect($suscriptor->fresh()->estaActivo())->toBeTrue();
});

it('no reactiva con un enlace de confirmacion anterior a la baja', function (): void {
    suscribirse(['email' => 'primo@correo.com']);
    $enlace = null;
    Mail::assertQueued(ConfirmacionSuscripcionMail::class, function (ConfirmacionSuscripcionMail $mail) use (&$enlace): bool {
        $enlace = $mail->urlConfirmacion;

        return true;
    });
    $suscriptor = Suscriptor::query()->sole();
    $this->get($enlace);
    $this->travel(1)->minute();
    $this->post(URL::signedRoute('suscripcion.baja', $suscriptor), ['_token' => 'x']);

    $this->get($enlace)
        ->assertRedirect('/')
        ->assertSessionHas('suscripcion', 'Ese enlace ya no sirve. Volvé a suscribirte para recibir uno nuevo.');

    expect($suscriptor->fresh()->estaActivo())->toBeFalse();
});

it('no manda otro correo de confirmacion al mismo email hasta que pase la espera', function (): void {
    suscribirse(['email' => 'vecino@correo.com']);
    suscribirse(['email' => 'vecino@correo.com']);
    Mail::assertQueuedCount(1);

    $this->travel(11)->minutes();
    suscribirse(['email' => 'vecino@correo.com']);
    Mail::assertQueuedCount(2);
});

it('limita los pedidos de suscripcion por dia desde la misma ip', function (): void {
    foreach (range(1, 20) as $numero) {
        suscribirse(['email' => "persona{$numero}@correo.com"])->assertRedirect('/');
        $this->travel(13)->minutes();
    }

    suscribirse(['email' => 'persona21@correo.com'])->assertTooManyRequests();
    Mail::assertQueuedCount(20);
});

it('suscribe sin confirmacion al usuario que ingreso con su email verificado', function (): void {
    $usuario = User::factory()->create(['email' => 'socio@correo.com', 'email_verified_at' => now()]);

    $this->actingAs($usuario);
    suscribirse(['email' => 'Socio@correo.com'])
        ->assertSessionHas('suscripcion', '¡Listo! Ya estás suscripto a los avisos de Torneos Mita\'í.');

    $suscriptor = Suscriptor::query()->sole();
    expect($suscriptor->estaActivo())->toBeTrue()
        ->and($suscriptor->usuario_id)->toBe($usuario->id);
    Mail::assertNothingOutgoing();
});

it('valida el correo y los avisos', function (array $datos, string $campo): void {
    suscribirse($datos)->assertSessionHasErrors($campo, null, 'suscripcion');

    expect(Suscriptor::query()->count())->toBe(0);
    Mail::assertNothingOutgoing();
})->with([
    'sin email' => [['email' => ''], 'email'],
    'email invalido' => [['email' => 'no-es-un-correo'], 'email'],
    'email como lista' => [['email' => ['a@correo.com']], 'email'],
    'dominio bloqueado' => [['email' => 'prueba@example.com'], 'email'],
    'aviso desconocido' => [['email' => 'a@correo.com', 'avisos' => ['spam']], 'avisos.0'],
]);
