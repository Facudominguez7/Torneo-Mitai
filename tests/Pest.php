<?php

use GuzzleHttp\Client as ClienteGuzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Resend\Client;
use Resend\Transporters\HttpTransporter;
use Resend\ValueObjects\ApiKey;
use Resend\ValueObjects\Transporter\BaseUri;
use Resend\ValueObjects\Transporter\Headers;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class, RefreshDatabase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

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
