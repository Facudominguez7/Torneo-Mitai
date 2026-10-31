<?php

// Aviso único a los usuarios migrados: sale por el mismo canal, auditoría y cola que el resumen,
// a todo el segmento (sin tópico) y una sola vez.

use App\Jobs\EnviarAvisoSuscriptores;
use App\Mail\AvisoMigracionSuscriptoresMail;
use App\Models\Edicion;
use App\Models\EnvioSuscriptores;
use App\Models\Suscriptor;
use App\Models\User;
use App\Services\EnvioSuscriptoresService;
use App\Services\SuscripcionService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config([
        'suscripciones.resend.sincronizar' => true,
        'suscripciones.resend.segmento' => 'segmento-sitio',
        'suscripciones.resend.topicos.resumen_fecha' => 'topico-resumen',
        'suscripciones.remitente' => 'avisos@notificaciones.torneosmitai.com',
        'mail.from.name' => 'Torneos Mitai',
    ]);
    Edicion::create(['nombre' => 'Edición Aviso']);
});

function procesarAviso(EnvioSuscriptores $envio): void
{
    (new EnviarAvisoSuscriptores($envio->id))->withFakeQueueInteractions()->handle(app(EnvioSuscriptoresService::class));
}

it('se niega a enviar mientras haya usuarios migrados sin sincronizar con resend', function (): void {
    Queue::fake();
    $historial = resendFalso([]);
    $usuario = User::factory()->create(['email' => 'migrado@correo.com']);
    Suscriptor::factory()->activo()->create(['email' => 'migrado@correo.com', 'usuario_id' => $usuario->id]);

    $this->artisan('suscripciones:avisar-migracion')
        ->expectsOutputToContain('Todavía hay 1 suscriptor(es) migrado(s) sin sincronizar con Resend')
        ->assertFailed();

    expect(EnvioSuscriptores::query()->count())->toBe(0)
        ->and($historial)->toHaveCount(0);
    Queue::assertNothingPushed();
});

it('en produccion registra el aviso y lo manda una vez como broadcast a todo el segmento, sin topico', function (): void {
    Queue::fake();
    $historial = resendFalso([respuestaResend(['id' => 'broadcast-aviso']), respuestaResend(['id' => 'broadcast-aviso'])]);
    $usuario = User::factory()->create(['email' => 'migrado@correo.com']);
    Suscriptor::factory()->activo()->create(['email' => 'migrado@correo.com', 'usuario_id' => $usuario->id])
        ->forceFill(['resend_sincronizado_at' => now()])->save();
    Suscriptor::factory()->activo()->create(['recibe_resumen_fecha' => false, 'recibe_avisos' => false, 'recibe_proxima_fecha' => false]);
    Suscriptor::factory()->deBaja()->create();

    $this->artisan('suscripciones:avisar-migracion')
        ->expectsOutputToContain('El aviso quedó en cola para 2 destinatario(s)')
        ->assertSuccessful();

    $envio = EnvioSuscriptores::query()->sole();
    expect($envio->only(['tipo', 'clave', 'estado', 'destinatarios', 'usuario_id']))->toBe([
        'tipo' => 'aviso_migracion', 'clave' => 'aviso_migracion', 'estado' => 'pendiente', 'destinatarios' => 2, 'usuario_id' => null,
    ])->and($historial)->toHaveCount(0);
    Queue::assertPushed(EnviarAvisoSuscriptores::class, fn (EnviarAvisoSuscriptores $job): bool => $job->envioId === $envio->id);

    procesarAviso($envio);

    expect($historial)->toHaveCount(2);
    $borrador = pedidoResend($historial, 0);
    expect($borrador['ruta'])->toBe('/broadcasts')
        ->and($borrador['cuerpo'])->toMatchArray([
            'segment_id' => 'segmento-sitio',
            'from' => 'Torneos Mitai <avisos@notificaciones.torneosmitai.com>',
            'subject' => "Los avisos de Torneos Mita'í ahora llegan desde una lista nueva",
        ])
        ->and($borrador['cuerpo'])->not->toHaveKey('topic_id')
        ->and($borrador['cuerpo']['html'])->toContain('aceptado recibir correos')->toContain('href="{{{contact.url_preferencias}}}"')
        ->and($borrador['cuerpo']['text'])->toContain('{{{contact.url_preferencias}}}')
        ->and(pedidoResend($historial, 1))->toMatchArray(['metodo' => 'POST', 'ruta' => '/broadcasts/broadcast-aviso/send'])
        ->and($envio->fresh()->only(['estado', 'broadcast_id', 'destinatarios']))->toBe(['estado' => 'enviado', 'broadcast_id' => 'broadcast-aviso', 'destinatarios' => 2]);
});

it('en local lo entrega por el mailer a todos los suscriptores activos, cada uno con su enlace, aunque la sincronizacion este apagada', function (): void {
    config(['suscripciones.resend.sincronizar' => false]);
    Queue::fake();
    Mail::fake();
    $historial = resendFalso([]);
    $usuario = User::factory()->create(['email' => 'migrado@correo.com']);
    $migrado = Suscriptor::factory()->activo()->create(['email' => 'migrado@correo.com', 'usuario_id' => $usuario->id]);
    $sinAvisos = Suscriptor::factory()->activo()->create(['email' => 'sin-avisos@correo.com', 'recibe_resumen_fecha' => false, 'recibe_avisos' => false, 'recibe_proxima_fecha' => false]);
    Suscriptor::factory()->pendiente()->create(['email' => 'pendiente@correo.com']);
    Suscriptor::factory()->deBaja()->create(['email' => 'baja@correo.com']);

    $this->artisan('suscripciones:avisar-migracion')->assertSuccessful();
    procesarAviso($envio = EnvioSuscriptores::query()->sole());

    expect($historial)->toHaveCount(0)
        ->and($envio->fresh()->only(['estado', 'destinatarios']))->toBe(['estado' => 'enviado', 'destinatarios' => 2]);
    Mail::assertSent(AvisoMigracionSuscriptoresMail::class, 2);
    foreach ([$migrado, $sinAvisos] as $suscriptor) {
        Mail::assertSent(AvisoMigracionSuscriptoresMail::class, fn (AvisoMigracionSuscriptoresMail $correo): bool => $correo->hasTo($suscriptor->email)
            && $correo->urlPreferencias === app(SuscripcionService::class)->urlPreferencias($suscriptor));
    }
});

it('una segunda corrida no lo vuelve a enviar e informa que ya salio', function (): void {
    config(['suscripciones.resend.sincronizar' => false]);
    Queue::fake();
    Mail::fake();
    Suscriptor::factory()->activo()->create();
    $this->artisan('suscripciones:avisar-migracion')->assertSuccessful();
    $this->travelTo(now()->addDay());
    procesarAviso($envio = EnvioSuscriptores::query()->sole());

    $this->artisan('suscripciones:avisar-migracion')
        ->expectsOutputToContain('El aviso de la migración ya se envió el '.$envio->fresh()->enviado_at->format('d/m/Y H:i').'.')
        ->assertSuccessful();

    expect(EnvioSuscriptores::query()->count())->toBe(1);
    Queue::assertPushed(EnviarAvisoSuscriptores::class, 1);
    Mail::assertSent(AvisoMigracionSuscriptoresMail::class, 1);
});

it('mientras esta en cola tampoco lo vuelve a encolar', function (): void {
    config(['suscripciones.resend.sincronizar' => false]);
    Queue::fake();
    Suscriptor::factory()->activo()->create();
    $this->artisan('suscripciones:avisar-migracion')->assertSuccessful();

    $this->artisan('suscripciones:avisar-migracion')
        ->expectsOutputToContain('El aviso de la migración ya está en cola.')
        ->assertSuccessful();

    Queue::assertPushed(EnviarAvisoSuscriptores::class, 1);
});

it('un aviso que fallo se puede volver a pedir y reutiliza su registro', function (): void {
    config(['suscripciones.resend.sincronizar' => false]);
    Queue::fake();
    Suscriptor::factory()->activo()->create();
    $this->artisan('suscripciones:avisar-migracion')->assertSuccessful();
    $envio = EnvioSuscriptores::query()->sole();
    (new EnviarAvisoSuscriptores($envio->id))->failed(new RuntimeException('caído'));
    expect($envio->fresh()->estado)->toBe('fallido');

    $this->artisan('suscripciones:avisar-migracion')
        ->expectsOutputToContain('El aviso quedó en cola para 1 destinatario(s)')
        ->assertSuccessful();

    expect(EnvioSuscriptores::query()->sole()->estado)->toBe('pendiente');
    Queue::assertPushed(EnviarAvisoSuscriptores::class, 2);
});

it('sin suscriptores activos no registra nada', function (): void {
    Queue::fake();
    Suscriptor::factory()->deBaja()->create();

    $this->artisan('suscripciones:avisar-migracion')
        ->expectsOutputToContain('No hay suscriptores activos para avisar.')
        ->assertFailed();

    expect(EnvioSuscriptores::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('los activos de un aviso salen de la configuracion de avisos y sin aviso son todos los activos', function (?string $aviso, int $cantidad): void {
    Suscriptor::factory()->activo()->create(['recibe_resumen_fecha' => true, 'recibe_avisos' => false, 'recibe_proxima_fecha' => false]);
    Suscriptor::factory()->activo()->create(['recibe_resumen_fecha' => false, 'recibe_avisos' => true, 'recibe_proxima_fecha' => true]);
    Suscriptor::factory()->deBaja()->create(['recibe_resumen_fecha' => true, 'recibe_avisos' => true, 'recibe_proxima_fecha' => true]);
    Suscriptor::factory()->pendiente()->create(['recibe_resumen_fecha' => true, 'recibe_avisos' => true, 'recibe_proxima_fecha' => true]);

    expect(Suscriptor::query()->recibenAviso($aviso)->count())->toBe($cantidad);
})->with([
    'resumen de la fecha' => ['resumen_fecha', 1],
    'avisos del torneo' => ['avisos', 1],
    'proxima fecha' => ['proxima_fecha', 1],
    'todo el segmento' => [null, 2],
]);

it('rechaza un aviso que no esta en la configuracion', function (): void {
    expect(fn () => Suscriptor::query()->recibenAviso('inexistente')->count())->toThrow(InvalidArgumentException::class);
});
