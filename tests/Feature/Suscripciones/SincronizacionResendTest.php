<?php

// Sincronización de suscriptores con los contactos, el segmento y los tópicos de Resend.

use App\Models\Suscriptor;
use App\Services\SuscripcionService;
use GuzzleHttp\Client as ClienteGuzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;
use Resend\Client;
use Resend\Transporters\HttpTransporter;
use Resend\ValueObjects\ApiKey;
use Resend\ValueObjects\Transporter\BaseUri;
use Resend\ValueObjects\Transporter\Headers;

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

/**
 * Reemplaza el cliente de Resend por uno que responde con $respuestas y guarda cada pedido.
 *
 * @param  list<Response>  $respuestas
 */
function resendFalso(array $respuestas): ArrayObject
{
    $historial = new ArrayObject;
    $pila = HandlerStack::create(new MockHandler($respuestas));
    $pila->push(Middleware::history($historial));
    $transporte = new HttpTransporter(
        new ClienteGuzzle(['handler' => $pila]),
        BaseUri::from('api.resend.com'),
        Headers::withAuthorization(ApiKey::from('re_prueba')),
    );
    app()->instance(Client::class, new Client($transporte));

    return $historial;
}

/** @param  array<string, mixed>  $cuerpo */
function respuestaResend(array $cuerpo, int $estado = 200): Response
{
    return new Response($estado, ['Content-Type' => 'application/json'], json_encode($cuerpo));
}

/** @return array{metodo: string, ruta: string, cuerpo: mixed} */
function pedidoResend(ArrayObject $historial, int $indice): array
{
    $pedido = $historial[$indice]['request'];

    return [
        'metodo' => $pedido->getMethod(),
        'ruta' => $pedido->getUri()->getPath(),
        'cuerpo' => json_decode((string) $pedido->getBody(), true),
    ];
}

it('crea el contacto en resend al confirmar, con el segmento y solo los avisos elegidos', function (): void {
    $historial = resendFalso([
        respuestaResend(['statusCode' => 404, 'name' => 'not_found', 'message' => 'Contact not found'], 404),
        respuestaResend(['object' => 'contact', 'id' => 'contacto-1']),
    ]);
    $suscriptor = Suscriptor::factory()->pendiente()->create([
        'email' => 'madre@correo.com',
        'recibe_resumen_fecha' => true,
        'recibe_avisos' => false,
        'recibe_proxima_fecha' => true,
    ]);

    app(SuscripcionService::class)->confirmar($suscriptor, now()->getTimestamp());

    expect($historial)->toHaveCount(2)
        ->and(pedidoResend($historial, 0))->toMatchArray(['metodo' => 'GET', 'ruta' => '/contacts/madre@correo.com'])
        ->and(pedidoResend($historial, 1))->toBe([
            'metodo' => 'POST',
            'ruta' => '/contacts',
            'cuerpo' => [
                'email' => 'madre@correo.com',
                'unsubscribed' => false,
                'segments' => [['id' => 'segmento-sitio']],
                'topics' => [
                    ['id' => 'topico-resumen', 'subscription' => 'opt_in'],
                    ['id' => 'topico-avisos', 'subscription' => 'opt_out'],
                    ['id' => 'topico-proxima', 'subscription' => 'opt_in'],
                ],
            ],
        ]);

    $suscriptor->refresh();
    expect($suscriptor->resend_contacto_id)->toBe('contacto-1')
        ->and($suscriptor->resend_sincronizado_at)->not->toBeNull();
});

it('reutiliza el contacto que ya existia en resend con ese correo', function (): void {
    $historial = resendFalso([
        respuestaResend(['object' => 'contact', 'id' => 'contacto-previo', 'email' => 'padre@correo.com']),
        respuestaResend(['object' => 'contact', 'id' => 'contacto-previo']),
        respuestaResend(['id' => 'segmento-sitio']),
        respuestaResend(['id' => 'contacto-previo']),
    ]);
    $suscriptor = Suscriptor::factory()->pendiente()->create(['email' => 'padre@correo.com']);

    app(SuscripcionService::class)->confirmar($suscriptor, now()->getTimestamp());

    expect(pedidoResend($historial, 1))->toBe(['metodo' => 'PATCH', 'ruta' => '/contacts/contacto-previo', 'cuerpo' => ['unsubscribed' => false]])
        ->and(pedidoResend($historial, 2))->toMatchArray(['metodo' => 'POST', 'ruta' => '/contacts/contacto-previo/segments/segmento-sitio'])
        ->and(pedidoResend($historial, 3))->toMatchArray(['metodo' => 'PATCH', 'ruta' => '/contacts/contacto-previo/topics'])
        ->and(pedidoResend($historial, 3)['cuerpo'])->toHaveCount(3)
        ->and($suscriptor->fresh()->resend_contacto_id)->toBe('contacto-previo');
});

it('marca la baja en resend', function (): void {
    $historial = resendFalso([respuestaResend(['object' => 'contact', 'id' => 'contacto-1'])]);
    $suscriptor = Suscriptor::factory()->activo()->create(['resend_contacto_id' => 'contacto-1']);

    app(SuscripcionService::class)->darDeBaja($suscriptor);

    expect($historial)->toHaveCount(1)
        ->and(pedidoResend($historial, 0))->toBe(['metodo' => 'PATCH', 'ruta' => '/contacts/contacto-1', 'cuerpo' => ['unsubscribed' => true]])
        ->and($suscriptor->fresh()->resend_sincronizado_at)->not->toBeNull();
});

it('si resend falla, la confirmacion sigue y el suscriptor queda pendiente de sincronizar sin loguear su correo', function (): void {
    Log::spy();
    resendFalso([respuestaResend(['statusCode' => 500, 'name' => 'internal_server_error', 'message' => 'Fallo con abuela@correo.com'], 500)]);
    $suscriptor = Suscriptor::factory()->pendiente()->create(['email' => 'abuela@correo.com']);

    $confirmado = app(SuscripcionService::class)->confirmar($suscriptor, now()->getTimestamp());

    $suscriptor->refresh();
    expect($confirmado)->toBeTrue()
        ->and($suscriptor->estaActivo())->toBeTrue()
        ->and($suscriptor->resend_sincronizado_at)->toBeNull();
    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $mensaje, array $contexto): bool => $contexto['suscriptor_id'] === $suscriptor->id
            && ! str_contains($mensaje.json_encode($contexto), 'abuela@correo.com'),
    );
});

it('no llama a resend cuando la sincronizacion esta apagada', function (): void {
    config(['suscripciones.resend.sincronizar' => false]);
    $historial = resendFalso([]);
    $suscriptor = Suscriptor::factory()->pendiente()->create();

    app(SuscripcionService::class)->confirmar($suscriptor, now()->getTimestamp());

    expect($historial)->toHaveCount(0)
        ->and($suscriptor->fresh()->resend_sincronizado_at)->toBeNull();
});

it('el comando sincroniza solo los suscriptores confirmados pendientes', function (): void {
    $historial = resendFalso([
        respuestaResend(['object' => 'contact', 'id' => 'contacto-1']),
    ]);
    $deBaja = Suscriptor::factory()->deBaja()->create(['resend_contacto_id' => 'contacto-1']);
    Suscriptor::factory()->pendiente()->create();
    Suscriptor::factory()->activo()->create(['resend_sincronizado_at' => now()]);

    $this->artisan('suscripciones:sincronizar')
        ->expectsOutput('Sincronizados: 1. Con error: 0.')
        ->assertSuccessful();

    expect($historial)->toHaveCount(1)
        ->and(pedidoResend($historial, 0)['ruta'])->toBe('/contacts/contacto-1')
        ->and($deBaja->fresh()->resend_sincronizado_at)->not->toBeNull();
});

it('el comando termina con error si algun suscriptor no se pudo sincronizar', function (): void {
    resendFalso([respuestaResend(['statusCode' => 429, 'name' => 'rate_limit_exceeded', 'message' => 'Too many requests'], 429)]);
    Suscriptor::factory()->deBaja()->create(['resend_contacto_id' => 'contacto-1']);

    $this->artisan('suscripciones:sincronizar')
        ->expectsOutput('Sincronizados: 0. Con error: 1.')
        ->assertFailed();
});
