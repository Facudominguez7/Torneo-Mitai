<?php

// Webhook de Resend: bajas y cambios de tópicos hechos desde allá vuelven a la base local.

use App\Models\Suscriptor;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

// Secreto de prueba armado en el test: no es una credencial real.
define('SECRETO_WEBHOOK_PRUEBA', 'whsec_'.base64_encode('clave-de-prueba-del-webhook-mitai'));

beforeEach(function (): void {
    config([
        'suscripciones.resend.webhook_secreto' => SECRETO_WEBHOOK_PRUEBA,
        'suscripciones.resend.topicos' => [
            'resumen_fecha' => 'topico-resumen',
            'avisos' => 'topico-avisos',
            'proxima_fecha' => 'topico-proxima',
        ],
    ]);
    Queue::fake();
});

/**
 * @return array<string, string>
 */
function encabezadosWebhook(string $cuerpo, string $id = 'msg_1', ?int $timestamp = null, ?string $secreto = SECRETO_WEBHOOK_PRUEBA): array
{
    $timestamp ??= now()->getTimestamp();
    $clave = base64_decode(substr((string) $secreto, 6));

    return [
        'svix-id' => $id,
        'svix-timestamp' => (string) $timestamp,
        'svix-signature' => 'v1,'.base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$cuerpo}", $clave, true)),
        'Content-Type' => 'application/json',
    ];
}

/**
 * @param  array<string, mixed>  $datos
 */
function eventoResend(string $tipo, array $datos, ?string $creado = null): string
{
    return json_encode(['type' => $tipo, 'created_at' => $creado ?? now()->toIso8601ZuluString('millisecond'), 'data' => $datos]);
}

function enviarWebhook(string $cuerpo, array $encabezados): TestResponse
{
    return test()->call('POST', route('webhooks.resend'), [], [], [], collect($encabezados)
        ->mapWithKeys(fn (string $valor, string $nombre): array => ['HTTP_'.strtoupper(str_replace('-', '_', $nombre)) => $valor])
        ->put('CONTENT_TYPE', 'application/json')
        ->all(), $cuerpo);
}

function suscriptorAnterior(array $atributos = []): Suscriptor
{
    return Suscriptor::factory()->activo()->create([
        'email' => 'madre@correo.com',
        'resend_sincronizado_at' => now()->subHour(),
        'cambio_local_ms' => now()->subHour()->getTimestampMs(),
        ...$atributos,
    ]);
}

it('da de baja al suscriptor que se desuscribio desde resend, sin volver a sincronizarlo', function (): void {
    $suscriptor = suscriptorAnterior();
    $cuerpo = eventoResend('contact.updated', ['id' => 'contacto-1', 'email' => 'Madre@Correo.com', 'unsubscribed' => true]);

    enviarWebhook($cuerpo, encabezadosWebhook($cuerpo))->assertNoContent();

    $suscriptor->refresh();
    expect($suscriptor->estaActivo())->toBeFalse()
        ->and($suscriptor->baja_at)->not->toBeNull()
        ->and($suscriptor->resend_sincronizado_at)->not->toBeNull();
    Queue::assertNothingPushed();
});

it('no reactiva a nadie cuando resend avisa un contacto suscripto', function (): void {
    $suscriptor = suscriptorAnterior(['baja_at' => now()->subHours(2)]);
    $cuerpo = eventoResend('contact.updated', ['id' => 'contacto-1', 'email' => 'madre@correo.com', 'unsubscribed' => false]);

    enviarWebhook($cuerpo, encabezadosWebhook($cuerpo))->assertNoContent();

    expect($suscriptor->fresh()->baja_at->equalTo($suscriptor->baja_at))->toBeTrue();
});

it('aplica los topicos que el suscriptor cambio desde resend', function (): void {
    $suscriptor = suscriptorAnterior(['recibe_resumen_fecha' => true, 'recibe_avisos' => false, 'recibe_proxima_fecha' => true]);
    $cuerpo = eventoResend('contact.topics.updated', ['email' => 'madre@correo.com', 'topics' => [
        ['id' => 'topico-resumen', 'subscription' => 'opt_out'],
        ['id' => 'topico-avisos', 'subscription' => 'opt_in'],
        ['id' => 'topico-de-otro-sitio', 'subscription' => 'opt_out'],
    ]]);

    enviarWebhook($cuerpo, encabezadosWebhook($cuerpo))->assertNoContent();

    $suscriptor->refresh();
    expect($suscriptor->recibe_resumen_fecha)->toBeFalse()
        ->and($suscriptor->recibe_avisos)->toBeTrue()
        ->and($suscriptor->recibe_proxima_fecha)->toBeTrue()
        ->and($suscriptor->estaActivo())->toBeTrue();
    Queue::assertNothingPushed();
});

it('da de baja y olvida el contacto cuando se borra en resend', function (): void {
    $suscriptor = suscriptorAnterior(['resend_contacto_id' => 'contacto-1']);
    $cuerpo = eventoResend('contact.deleted', ['id' => 'contacto-1', 'email' => 'madre@correo.com', 'unsubscribed' => false]);

    enviarWebhook($cuerpo, encabezadosWebhook($cuerpo))->assertNoContent();

    $suscriptor->refresh();
    expect($suscriptor->estaActivo())->toBeFalse()
        ->and($suscriptor->resend_contacto_id)->toBeNull();
});

it('deja pendiente el cambio local que todavia no llego a resend', function (): void {
    $suscriptor = suscriptorAnterior(['resend_sincronizado_at' => null]);
    $cuerpo = eventoResend('contact.updated', ['id' => 'contacto-1', 'email' => 'madre@correo.com', 'unsubscribed' => true]);

    enviarWebhook($cuerpo, encabezadosWebhook($cuerpo))->assertNoContent();

    $suscriptor->refresh();
    expect($suscriptor->estaActivo())->toBeFalse()
        ->and($suscriptor->resend_sincronizado_at)->toBeNull();
});

it('aplica en orden los eventos seguidos aunque el anterior se haya procesado despues', function (): void {
    $suscriptor = suscriptorAnterior(['recibe_resumen_fecha' => true]);
    $topicos = eventoResend('contact.topics.updated', ['email' => 'madre@correo.com', 'topics' => [['id' => 'topico-resumen', 'subscription' => 'opt_out']]], now()->subSeconds(10)->toIso8601ZuluString('millisecond'));
    $baja = eventoResend('contact.updated', ['email' => 'madre@correo.com', 'unsubscribed' => true], now()->subSeconds(8)->toIso8601ZuluString('millisecond'));

    enviarWebhook($topicos, encabezadosWebhook($topicos, 'msg_topicos'))->assertNoContent();
    enviarWebhook($baja, encabezadosWebhook($baja, 'msg_baja'))->assertNoContent();

    $suscriptor->refresh();
    expect($suscriptor->recibe_resumen_fecha)->toBeFalse()
        ->and($suscriptor->estaActivo())->toBeFalse();
});

it('ignora el evento anterior al ultimo cambio local', function (): void {
    $suscriptor = suscriptorAnterior(['cambio_local_ms' => now()->subSecond()->getTimestampMs()]);
    $cuerpo = eventoResend('contact.updated', ['email' => 'madre@correo.com', 'unsubscribed' => true], now()->subSeconds(2)->toIso8601ZuluString('millisecond'));

    enviarWebhook($cuerpo, encabezadosWebhook($cuerpo))->assertNoContent();

    expect($suscriptor->fresh()->estaActivo())->toBeTrue();
});

it('procesa una sola vez el mismo evento aunque resend lo reintente', function (): void {
    $suscriptor = suscriptorAnterior(['recibe_resumen_fecha' => true]);
    $cuerpo = eventoResend('contact.topics.updated', ['email' => 'madre@correo.com', 'topics' => [['id' => 'topico-resumen', 'subscription' => 'opt_out']]]);

    enviarWebhook($cuerpo, encabezadosWebhook($cuerpo, 'msg_repetido'))->assertNoContent();
    Suscriptor::query()->whereKey($suscriptor->id)->update(['recibe_resumen_fecha' => true]);
    enviarWebhook($cuerpo, encabezadosWebhook($cuerpo, 'msg_repetido'))->assertNoContent();

    expect($suscriptor->fresh()->recibe_resumen_fecha)->toBeTrue();
});

it('responde sin cambios a un correo que no es suscriptor o a eventos que no usa', function (string $cuerpo): void {
    $suscriptor = suscriptorAnterior();

    enviarWebhook($cuerpo, encabezadosWebhook($cuerpo))->assertNoContent();

    expect($suscriptor->fresh()->estaActivo())->toBeTrue();
})->with([
    'correo desconocido' => fn (): string => eventoResend('contact.updated', ['email' => 'otra@correo.com', 'unsubscribed' => true]),
    'evento de envio' => fn (): string => eventoResend('email.bounced', ['email' => 'madre@correo.com']),
]);

it('rechaza el pedido sin una firma valida y no toca al suscriptor', function (string $caso): void {
    $suscriptor = suscriptorAnterior();
    $cuerpo = eventoResend('contact.updated', ['email' => 'madre@correo.com', 'unsubscribed' => true]);

    $encabezados = match ($caso) {
        'otro secreto' => encabezadosWebhook($cuerpo, secreto: 'whsec_'.base64_encode('otro-secreto-de-32-caracteres!!')),
        'cuerpo alterado' => encabezadosWebhook(str_replace('true', 'false', $cuerpo)),
        'firma vieja' => encabezadosWebhook($cuerpo, timestamp: now()->subMinutes(10)->getTimestamp()),
        'sin encabezados' => ['Content-Type' => 'application/json'],
    };

    enviarWebhook($cuerpo, $encabezados)->assertUnauthorized();

    expect($suscriptor->fresh()->estaActivo())->toBeTrue();
})->with(['otro secreto', 'cuerpo alterado', 'firma vieja', 'sin encabezados']);

it('rechaza todo si no esta configurado el secreto', function (): void {
    config(['suscripciones.resend.webhook_secreto' => null]);
    $cuerpo = eventoResend('contact.updated', ['email' => 'madre@correo.com', 'unsubscribed' => true]);

    enviarWebhook($cuerpo, encabezadosWebhook($cuerpo))->assertUnauthorized();
});

it('acepta la firma valida entre varias mientras se rota el secreto', function (): void {
    $suscriptor = suscriptorAnterior();
    $cuerpo = eventoResend('contact.updated', ['email' => 'madre@correo.com', 'unsubscribed' => true]);
    $encabezados = encabezadosWebhook($cuerpo);
    $encabezados['svix-signature'] = 'v1,firmaVieja= '.$encabezados['svix-signature'];

    enviarWebhook($cuerpo, $encabezados)->assertNoContent();

    expect($suscriptor->fresh()->estaActivo())->toBeFalse();
});

it('no pide token csrf, porque resend no lo manda', function (): void {
    app(Kernel::class);

    expect(app(ValidateCsrfToken::class)->getExcludedPaths())->toContain('webhooks/resend');
});
