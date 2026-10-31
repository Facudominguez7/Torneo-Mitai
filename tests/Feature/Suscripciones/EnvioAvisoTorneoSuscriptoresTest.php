<?php

// Aviso del torneo: un mensaje libre del administrador para los suscriptores que eligieron los
// avisos. Sale por el mismo canal, auditoría y cola; cada carga del formulario se envía una sola vez.

use App\Jobs\EnviarAvisoSuscriptores;
use App\Mail\AvisoTorneoMail;
use App\Models\Edicion;
use App\Models\EnvioSuscriptores;
use App\Models\Suscriptor;
use App\Models\User;
use App\Services\EnvioSuscriptoresService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    config([
        'suscripciones.resend.sincronizar' => true,
        'suscripciones.resend.segmento' => 'segmento-sitio',
        'suscripciones.resend.topicos.avisos' => 'topico-avisos',
        'suscripciones.remitente' => 'avisos@notificaciones.torneosmitai.com',
        'mail.from.name' => 'Torneos Mitai',
    ]);
    Edicion::create(['nombre' => 'Edición Avisos']);
});

function adminAvisoTorneo(): User
{
    return User::factory()->create(['rol' => 'admin', 'email_verified_at' => now(), 'email' => 'admin-avisos@torneomitai.test']);
}

/** @param  array<string, mixed>  $datos */
function enviarAvisoTorneo(object $prueba, User $admin, array $datos): TestResponse
{
    return $prueba->actingAs($admin)
        ->withSession(['_token' => 'token-avisos'])
        ->postJson(route('notificaciones.avisos.suscriptores'), $datos, ['X-CSRF-TOKEN' => 'token-avisos']);
}

/** @return array<string, string> */
function avisoValido(array $cambios = []): array
{
    return [
        'id_envio' => (string) Str::uuid(),
        'titulo' => 'Se suspende la fecha por lluvia',
        'mensaje' => "Los partidos del sábado pasan al domingo.\nLos horarios se mantienen.",
        ...$cambios,
    ];
}

function procesarAvisoTorneo(EnvioSuscriptores $envio): void
{
    (new EnviarAvisoSuscriptores($envio->id))->withFakeQueueInteractions()->handle(app(EnvioSuscriptoresService::class));
}

it('cuenta a quienes eligieron los avisos del torneo', function (): void {
    Suscriptor::factory()->activo()->count(2)->create(['recibe_avisos' => true]);
    Suscriptor::factory()->activo()->create(['recibe_avisos' => false]);

    $this->actingAs(adminAvisoTorneo())
        ->getJson(route('notificaciones.avisos.contar'))
        ->assertOk()
        ->assertJson(['success' => true, 'destinatarios' => 2, 'message' => 'Se enviará a 2 destinatario(s).']);
});

it('registra el aviso con su contenido en la auditoria y lo encola', function (): void {
    Queue::fake();
    Suscriptor::factory()->activo()->count(3)->create(['recibe_avisos' => true]);
    $admin = adminAvisoTorneo();
    $aviso = avisoValido();

    enviarAvisoTorneo($this, $admin, $aviso)
        ->assertAccepted()
        ->assertJson([
            'success' => true,
            'estado' => 'pendiente',
            'destinatarios' => 3,
            'message' => 'El aviso quedó en cola para 3 destinatario(s): sale en el próximo minuto.',
        ]);

    $envio = EnvioSuscriptores::query()->sole();
    expect($envio->only(['tipo', 'clave', 'usuario_id', 'estado', 'destinatarios']))->toBe([
        'tipo' => 'avisos',
        'clave' => 'avisos:'.$aviso['id_envio'],
        'usuario_id' => $admin->id,
        'estado' => 'pendiente',
        'destinatarios' => 3,
    ])
        ->and($envio->contenido)->toBe(['titulo' => $aviso['titulo'], 'mensaje' => $aviso['mensaje']])
        ->and($envio->idEdicion)->toBe((int) Edicion::query()->value('id'));
    Queue::assertPushed(EnviarAvisoSuscriptores::class, 1);
});

it('el mismo formulario no manda el aviso dos veces', function (): void {
    Queue::fake();
    Suscriptor::factory()->activo()->create(['recibe_avisos' => true]);
    $admin = adminAvisoTorneo();
    $aviso = avisoValido();

    enviarAvisoTorneo($this, $admin, $aviso)->assertAccepted();
    enviarAvisoTorneo($this, $admin, $aviso)
        ->assertConflict()
        ->assertJson(['success' => false, 'message' => 'Este aviso ya está en cola para enviarse.']);

    EnvioSuscriptores::query()->sole()->forceFill(['estado' => 'enviado', 'enviado_at' => '2026-11-01 18:40:00'])->save();

    enviarAvisoTorneo($this, $admin, $aviso)
        ->assertConflict()
        ->assertJson(['success' => false, 'message' => 'Este aviso ya se envió a los suscriptores el 01/11/2026 18:40.']);

    expect(EnvioSuscriptores::query()->count())->toBe(1);
    Queue::assertPushed(EnviarAvisoSuscriptores::class, 1);
});

it('valida el titulo, el mensaje y el identificador del envio', function (array $cambios, string $campo): void {
    Queue::fake();
    Suscriptor::factory()->activo()->create(['recibe_avisos' => true]);

    enviarAvisoTorneo($this, adminAvisoTorneo(), avisoValido($cambios))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$campo]);

    expect(EnvioSuscriptores::query()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'sin titulo' => [['titulo' => ''], 'titulo'],
    'titulo largo' => [['titulo' => str_repeat('a', 121)], 'titulo'],
    'sin mensaje' => [['mensaje' => '   '], 'mensaje'],
    'mensaje largo' => [['mensaje' => str_repeat('a', 2001)], 'mensaje'],
    'sin identificador' => [['id_envio' => ''], 'id_envio'],
    'identificador invalido' => [['id_envio' => 'no-es-uuid'], 'id_envio'],
]);

it('sin suscriptores a los avisos no registra nada', function (): void {
    Queue::fake();
    Suscriptor::factory()->activo()->create(['recibe_avisos' => false]);

    enviarAvisoTorneo($this, adminAvisoTorneo(), avisoValido())
        ->assertUnprocessable()
        ->assertJson(['success' => false, 'message' => 'Todavía nadie eligió recibir los avisos del torneo.']);

    expect(EnvioSuscriptores::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('en produccion lo manda como broadcast al topico de avisos con el mensaje escapado', function (): void {
    Queue::fake();
    $historial = resendFalso([
        respuestaResend(['id' => 'b-aviso']),
        respuestaResend(['id' => 'b-aviso']),
    ]);
    Suscriptor::factory()->activo()->count(2)->create(['recibe_avisos' => true]);
    enviarAvisoTorneo($this, adminAvisoTorneo(), avisoValido(['mensaje' => "Primera línea <b>importante</b>\nSegunda línea"]))->assertAccepted();

    procesarAvisoTorneo(EnvioSuscriptores::query()->sole());

    $borrador = pedidoResend($historial, 0);
    expect($borrador['ruta'])->toBe('/broadcasts')
        ->and($borrador['cuerpo']['topic_id'])->toBe('topico-avisos')
        ->and($borrador['cuerpo']['subject'])->toBe("Se suspende la fecha por lluvia - Torneos Mita'i")
        ->and($borrador['cuerpo']['html'])->toContain('Primera línea &lt;b&gt;importante&lt;/b&gt;<br />')->toContain('Segunda línea')
        ->and($borrador['cuerpo']['html'])->not->toContain('<b>importante</b>')
        ->and($borrador['cuerpo']['html'])->toContain('href="{{{contact.url_preferencias}}}"')
        ->and($borrador['cuerpo']['text'])->toContain("Primera línea <b>importante</b>\nSegunda línea")->toContain('{{{contact.url_preferencias}}}')
        ->and(EnvioSuscriptores::query()->sole()->only(['estado', 'destinatarios', 'broadcast_id']))
        ->toBe(['estado' => 'enviado', 'destinatarios' => 2, 'broadcast_id' => 'b-aviso']);
});

it('en local lo entrega por el mailer solo a quienes eligieron los avisos', function (): void {
    config(['suscripciones.resend.sincronizar' => false]);
    Queue::fake();
    Mail::fake();
    $elegidos = Suscriptor::factory()->activo()->count(2)->create(['recibe_avisos' => true]);
    Suscriptor::factory()->activo()->create(['recibe_avisos' => false]);
    enviarAvisoTorneo($this, adminAvisoTorneo(), avisoValido())->assertAccepted();

    procesarAvisoTorneo(EnvioSuscriptores::query()->sole());

    Mail::assertSent(AvisoTorneoMail::class, 2);
    foreach ($elegidos as $suscriptor) {
        Mail::assertSent(AvisoTorneoMail::class, fn (AvisoTorneoMail $correo): bool => $correo->hasTo($suscriptor->email));
    }
});

it('el panel muestra la tarjeta del aviso con un identificador de envio por carga', function (): void {
    $respuesta = $this->actingAs(adminAvisoTorneo())->get(route('notificaciones.index'))->assertOk();

    $respuesta->assertSee('Aviso del torneo')->assertSee('id="btn-enviar-aviso-torneo"', false);
    expect(preg_match('/id="aviso-idEnvio" value="([0-9a-f-]{36})"/', $respuesta->getContent(), $coincidencia))->toBe(1)
        ->and(Str::isUuid($coincidencia[1]))->toBeTrue();
});
