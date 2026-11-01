<?php

// La sincronización con Resend sale de la petición: cada cambio encola un job por suscriptor.

use App\Jobs\SincronizarSuscriptorResend;
use App\Models\Suscriptor;
use App\Services\SincronizacionResendService;
use App\Services\SuscripcionService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config([
        'suscripciones.resend.sincronizar' => true,
        'suscripciones.resend.segmento' => 'segmento-sitio',
        'suscripciones.resend.topicos' => [
            'resumen_fecha' => 'topico-resumen',
            'avisos' => 'topico-avisos',
            'proxima_fecha' => 'topico-proxima',
        ],
        'suscripciones.resend.pausa_ms' => 0,
    ]);
});

it('confirmar, cambiar preferencias y dar de baja seguidos encolan una sola sincronizacion sin llamar a resend', function (): void {
    Queue::fake();
    $historial = resendFalso([]);
    $suscriptor = Suscriptor::factory()->pendiente()->create();
    $servicio = app(SuscripcionService::class);

    $servicio->confirmar($suscriptor, now()->getTimestamp());
    $servicio->actualizarPreferencias($suscriptor->fresh(), ['avisos']);
    $servicio->darDeBaja($suscriptor->fresh());

    expect($historial)->toHaveCount(0);
    Queue::assertPushed(SincronizarSuscriptorResend::class, 1);
    Queue::assertPushed(SincronizarSuscriptorResend::class, fn (SincronizarSuscriptorResend $job): bool => $job->suscriptorId === $suscriptor->id
        && $job->afterCommit === true
        && $job->uniqueId() === (string) $suscriptor->id);
});

it('cada suscriptor tiene su propia sincronizacion en cola', function (): void {
    Queue::fake();
    [$primero, $segundo] = Suscriptor::factory()->pendiente()->count(2)->create();

    app(SuscripcionService::class)->confirmar($primero, now()->getTimestamp());
    app(SuscripcionService::class)->confirmar($segundo, now()->getTimestamp());

    Queue::assertPushed(SincronizarSuscriptorResend::class, 2);
    expect(new SincronizarSuscriptorResend(1))->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class)
        ->and((new SincronizarSuscriptorResend(1))->tries)->toBe(5);
});

it('lee el estado del momento: sincroniza la baja aunque se haya encolado al confirmar', function (): void {
    $historial = resendFalso([respuestaResend(['object' => 'contact', 'id' => 'contacto-1'])]);
    $suscriptor = Suscriptor::factory()->deBaja()->create(['resend_contacto_id' => 'contacto-1']);

    (new SincronizarSuscriptorResend($suscriptor->id))->withFakeQueueInteractions()->handle(app(SincronizacionResendService::class));

    expect($historial)->toHaveCount(1)
        ->and(pedidoResend($historial, 0))->toMatchArray(['metodo' => 'PATCH', 'ruta' => '/contacts/contacto-1'])
        ->and($suscriptor->fresh()->resend_sincronizado_at)->not->toBeNull();
});

it('si resend falla vuelve a la cola con espera creciente', function (int $intento, int $espera): void {
    resendFalso([respuestaResend(['statusCode' => 500, 'name' => 'internal_server_error', 'message' => 'Fallo'], 500)]);
    $suscriptor = Suscriptor::factory()->deBaja()->create(['resend_contacto_id' => 'contacto-1']);

    $job = (new SincronizarSuscriptorResend($suscriptor->id))->withFakeQueueInteractions();
    $job->job->attempts = $intento;
    $job->handle(app(SincronizacionResendService::class));

    $job->assertReleased($espera);
    expect($suscriptor->fresh()->resend_sincronizado_at)->toBeNull();
})->with([
    'primer intento' => [1, 60],
    'segundo intento' => [2, 300],
    'tercer intento' => [3, 1800],
    'cuarto intento' => [4, 1800],
]);

it('no hace nada si el suscriptor ya esta sincronizado, no existe o la sincronizacion esta apagada', function (): void {
    $historial = resendFalso([]);
    $sincronizado = Suscriptor::factory()->activo()->create(['resend_sincronizado_at' => now()]);
    $pendiente = Suscriptor::factory()->deBaja()->create(['resend_contacto_id' => 'contacto-1']);
    $servicio = app(SincronizacionResendService::class);

    foreach ([$sincronizado->id, 999999] as $id) {
        $job = (new SincronizarSuscriptorResend($id))->withFakeQueueInteractions();
        $job->handle($servicio);
        $job->assertNotReleased();
    }

    config(['suscripciones.resend.sincronizar' => false]);
    $job = (new SincronizarSuscriptorResend($pendiente->id))->withFakeQueueInteractions();
    $job->handle($servicio);
    $job->assertNotReleased();

    expect($historial)->toHaveCount(0);
});

it('un cambio guardado mientras corre la sincronizacion queda pendiente y lo lleva el job siguiente', function (): void {
    $historial = resendFalso([
        respuestaResend(['object' => 'contact', 'id' => 'contacto-1']),
        respuestaResend(['object' => 'contact', 'id' => 'contacto-1']),
        respuestaResend(['object' => 'contact', 'id' => 'contacto-1']),
        respuestaResend(['object' => 'contact', 'id' => 'contacto-1']),
    ]);
    $suscriptor = Suscriptor::factory()->activo()->create(['resend_contacto_id' => 'contacto-1', 'cambio_local_ms' => now()->subMinute()->getTimestampMs()]);
    $leidoPorElPrimero = $suscriptor->fresh();

    // La persona guarda preferencias mientras el primer job todavía está hablando con Resend.
    Queue::fake();
    app(SuscripcionService::class)->actualizarPreferencias($suscriptor->fresh(), ['avisos']);
    app(SincronizacionResendService::class)->sincronizar($leidoPorElPrimero);

    expect($suscriptor->fresh()->resend_sincronizado_at)->toBeNull();

    (new SincronizarSuscriptorResend($suscriptor->id))->withFakeQueueInteractions()->handle(app(SincronizacionResendService::class));

    expect($historial)->toHaveCount(4)
        ->and(pedidoResend($historial, 3)['cuerpo'])->toContain(['id' => 'topico-avisos', 'subscription' => 'opt_in'])
        ->and($suscriptor->fresh()->resend_sincronizado_at)->not->toBeNull();
});
