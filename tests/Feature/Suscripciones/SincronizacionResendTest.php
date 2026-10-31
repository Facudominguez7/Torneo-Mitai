<?php

// Sincronización de suscriptores con los contactos, el segmento y los tópicos de Resend.

use App\Models\Suscriptor;
use App\Services\SuscripcionService;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;

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
                'properties' => ['url_preferencias' => app(SuscripcionService::class)->urlPreferencias($suscriptor)],
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

    expect(pedidoResend($historial, 1))->toBe(['metodo' => 'PATCH', 'ruta' => '/contacts/contacto-previo', 'cuerpo' => [
        'unsubscribed' => false,
        'properties' => ['url_preferencias' => app(SuscripcionService::class)->urlPreferencias($suscriptor)],
    ]])
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

it('guarda el contacto encontrado aunque falle un paso posterior, y la baja llega a ese contacto', function (): void {
    resendFalso([
        respuestaResend(['object' => 'contact', 'id' => 'contacto-previo']),
        respuestaResend(['object' => 'contact', 'id' => 'contacto-previo']),
        respuestaResend(['id' => 'segmento-sitio']),
        respuestaResend(['statusCode' => 429, 'name' => 'rate_limit_exceeded', 'message' => 'Too many requests'], 429),
    ]);
    $suscriptor = Suscriptor::factory()->pendiente()->create(['email' => 'tio@correo.com']);
    app(SuscripcionService::class)->confirmar($suscriptor, now()->getTimestamp());

    $suscriptor->refresh();
    expect($suscriptor->resend_contacto_id)->toBe('contacto-previo')
        ->and($suscriptor->resend_sincronizado_at)->toBeNull();

    $historial = resendFalso([respuestaResend(['object' => 'contact', 'id' => 'contacto-previo'])]);
    app(SuscripcionService::class)->darDeBaja($suscriptor);

    expect($historial)->toHaveCount(1)
        ->and(pedidoResend($historial, 0))->toBe(['metodo' => 'PATCH', 'ruta' => '/contacts/contacto-previo', 'cuerpo' => ['unsubscribed' => true]])
        ->and($suscriptor->fresh()->resend_sincronizado_at)->not->toBeNull();
});

it('busca el contacto por correo al dar de baja si no tiene id guardado', function (): void {
    resendFalso([
        respuestaResend(['statusCode' => 404, 'name' => 'not_found', 'message' => 'Contact not found'], 404),
        new ConnectException('Timeout', new Request('POST', 'contacts')),
    ]);
    $suscriptor = Suscriptor::factory()->pendiente()->create(['email' => 'sobrino@correo.com']);
    app(SuscripcionService::class)->confirmar($suscriptor, now()->getTimestamp());
    expect($suscriptor->fresh()->resend_contacto_id)->toBeNull();

    $historial = resendFalso([
        respuestaResend(['object' => 'contact', 'id' => 'contacto-remoto']),
        respuestaResend(['object' => 'contact', 'id' => 'contacto-remoto']),
    ]);
    app(SuscripcionService::class)->darDeBaja($suscriptor->fresh());

    expect($historial)->toHaveCount(2)
        ->and(pedidoResend($historial, 0))->toMatchArray(['metodo' => 'GET', 'ruta' => '/contacts/sobrino@correo.com'])
        ->and(pedidoResend($historial, 1))->toBe(['metodo' => 'PATCH', 'ruta' => '/contacts/contacto-remoto', 'cuerpo' => ['unsubscribed' => true]])
        ->and($suscriptor->fresh()->resend_contacto_id)->toBe('contacto-remoto');
});

it('no marca nada en resend si quien se da de baja nunca llego a ser contacto', function (): void {
    $historial = resendFalso([respuestaResend(['statusCode' => 404, 'name' => 'not_found', 'message' => 'Contact not found'], 404)]);
    $suscriptor = Suscriptor::factory()->activo()->create(['email' => 'nuera@correo.com']);

    app(SuscripcionService::class)->darDeBaja($suscriptor);

    expect($historial)->toHaveCount(1)
        ->and(pedidoResend($historial, 0)['metodo'])->toBe('GET')
        ->and($suscriptor->fresh()->resend_sincronizado_at)->not->toBeNull();
});

it('el comando avisa que la sincronizacion esta apagada sin tocar a nadie', function (): void {
    config(['suscripciones.resend.sincronizar' => false]);
    $historial = resendFalso([]);
    Suscriptor::factory()->activo()->create();

    $this->artisan('suscripciones:sincronizar')
        ->expectsOutput('La sincronización con Resend está apagada (el mailer de suscriptores no es resend).')
        ->assertSuccessful();

    expect($historial)->toHaveCount(0);
});
