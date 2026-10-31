<?php

// Envío del resumen de la fecha a los suscriptores: se registra en la auditoría, se encola y el job
// lo manda como broadcast de Resend o, sin sincronización, por el mailer local.

use App\Jobs\EnviarAvisoSuscriptores;
use App\Mail\ResumenResultadosFechaMail;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\EnvioSuscriptores;
use App\Models\Equipo;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Partido;
use App\Models\Suscriptor;
use App\Models\User;
use App\Services\EnvioSuscriptores\CanalBroadcastResend;
use App\Services\EnvioSuscriptores\CanalEnvioSuscriptores;
use App\Services\EnvioSuscriptores\CanalMailerLocal;
use App\Services\EnvioSuscriptoresService;
use App\Services\SuscripcionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Cache::flush();
    config([
        'suscripciones.resend.sincronizar' => true,
        'suscripciones.resend.segmento' => 'segmento-sitio',
        'suscripciones.resend.topicos.resumen_fecha' => 'topico-resumen',
        'suscripciones.remitente' => 'avisos@notificaciones.torneosmitai.com',
        'mail.from.name' => 'Torneos Mitai',
    ]);
});

/** @return array{fecha: Fecha, categoria: Categoria} */
function fechaConResultados(bool $conResultados = true): array
{
    $edicion = Edicion::create(['nombre' => 'Edición Resumen']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 12']);
    $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'A']);
    $fecha = Fecha::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Fecha 3']);
    $local = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Halcones', 'foto' => 'h.png']);
    $visitante = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Pumitas', 'foto' => 'p.png']);

    Partido::create([
        'idCategoria' => $categoria->id,
        'idFechas' => $fecha->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'idGrupo' => $grupo->id,
        'idEdicion' => $edicion->id,
        'golesEquipoLocal' => $conResultados ? 3 : null,
        'golesEquipoVisitante' => $conResultados ? 1 : null,
        'horario_datetime' => now()->subDay()->format('Y-m-d H:i:s'),
        'cancha' => 1,
        'jugado' => $conResultados ? 1 : 0,
    ]);

    return ['fecha' => $fecha, 'categoria' => $categoria];
}

function adminEnvioResumen(): User
{
    return User::factory()->create(['rol' => 'admin', 'email_verified_at' => now()]);
}

function enviarResumenASuscriptores(object $prueba, ?User $usuario, array $datos): TestResponse
{
    return $prueba->actingAs($usuario ?? adminEnvioResumen())
        ->withSession(['_token' => 'token-resumen'])
        ->postJson(route('notificaciones.resumen-fecha.suscriptores'), $datos, ['X-CSRF-TOKEN' => 'token-resumen']);
}

/** @param  array<string, mixed>  $atributos */
function envioRegistrado(Fecha $fecha, array $atributos = []): EnvioSuscriptores
{
    return EnvioSuscriptores::query()->create([
        'tipo' => 'resumen_fecha', 'clave' => "resumen_fecha:{$fecha->id}:{$fecha->idCategoria}", 'idEdicion' => $fecha->idEdicion,
        'idFecha' => $fecha->id, 'idCategoria' => $fecha->idCategoria, 'estado' => 'pendiente', ...$atributos,
    ]);
}

function procesarEnvio(EnvioSuscriptores $envio): EnviarAvisoSuscriptores
{
    $job = (new EnviarAvisoSuscriptores($envio->id))->withFakeQueueInteractions();
    $job->handle(app(EnvioSuscriptoresService::class));

    return $job;
}

it('registra el envio en la auditoria y lo deja en cola sin llamar a resend', function (): void {
    Queue::fake();
    $historial = resendFalso([]);
    ['fecha' => $fecha, 'categoria' => $categoria] = fechaConResultados();
    Suscriptor::factory()->activo()->count(2)->create(['recibe_resumen_fecha' => true]);
    $admin = adminEnvioResumen();

    enviarResumenASuscriptores($this, $admin, ['idFecha' => $fecha->id])
        ->assertAccepted()
        ->assertJson([
            'success' => true,
            'estado' => 'pendiente',
            'destinatarios' => 2,
            'message' => 'El resumen quedó en cola para 2 destinatario(s): sale en el próximo minuto.',
        ]);

    $envio = EnvioSuscriptores::query()->sole();
    expect($envio->only(['tipo', 'clave', 'idFecha', 'idCategoria', 'usuario_id', 'estado', 'destinatarios']))->toBe([
        'tipo' => 'resumen_fecha',
        'clave' => "resumen_fecha:{$fecha->id}:{$categoria->id}",
        'idFecha' => $fecha->id,
        'idCategoria' => $categoria->id,
        'usuario_id' => $admin->id,
        'estado' => 'pendiente',
        'destinatarios' => 2,
    ])->and($historial)->toHaveCount(0);
    Queue::assertPushed(EnviarAvisoSuscriptores::class, fn (EnviarAvisoSuscriptores $job): bool => $job->envioId === $envio->id && $job->afterCommit === true);
});

it('el job crea el broadcast como borrador, guarda su id y recien despues lo manda', function (): void {
    Queue::fake();
    $historial = resendFalso([respuestaResend(['id' => 'broadcast-1']), respuestaResend(['id' => 'broadcast-1'])]);
    ['fecha' => $fecha] = fechaConResultados();
    Suscriptor::factory()->activo()->create(['recibe_resumen_fecha' => true]);
    enviarResumenASuscriptores($this, null, ['idFecha' => $fecha->id])->assertAccepted();

    procesarEnvio($envio = EnvioSuscriptores::query()->sole())->assertNotReleased();

    expect($historial)->toHaveCount(2);
    $borrador = pedidoResend($historial, 0);
    expect($borrador['metodo'])->toBe('POST')
        ->and($borrador['ruta'])->toBe('/broadcasts')
        ->and($borrador['cuerpo'])->toMatchArray([
            'segment_id' => 'segmento-sitio',
            'topic_id' => 'topico-resumen',
            'from' => 'Torneos Mitai <avisos@notificaciones.torneosmitai.com>',
            'subject' => "Resultados Fecha 3 - Torneos Mita'i (Sub 12)",
        ])
        ->and($borrador['cuerpo'])->not->toHaveKey('send')
        ->and($borrador['cuerpo']['html'])->toContain('Halcones')->toContain('Pumitas')
        ->toContain('href="{{{contact.url_preferencias}}}"')
        ->and($borrador['cuerpo']['text'])->toContain('{{{contact.url_preferencias}}}')
        ->and(pedidoResend($historial, 1))->toMatchArray(['metodo' => 'POST', 'ruta' => '/broadcasts/broadcast-1/send']);

    $envio->refresh();
    expect($envio->estado)->toBe('enviado')
        ->and($envio->broadcast_id)->toBe('broadcast-1')
        ->and($envio->destinatarios)->toBe(1)
        ->and($envio->enviado_at)->not->toBeNull();
});

it('un reintento actualiza el borrador con los resultados del momento y no lo manda dos veces', function (): void {
    $historial = resendFalso([
        respuestaResend(['object' => 'broadcast', 'id' => 'broadcast-1', 'status' => 'draft']),
        respuestaResend(['id' => 'broadcast-1']),
        respuestaResend(['id' => 'broadcast-1']),
    ]);
    ['fecha' => $fecha] = fechaConResultados();
    $envio = envioRegistrado($fecha, ['broadcast_id' => 'broadcast-1']);
    Partido::query()->update(['golesEquipoLocal' => 5]);

    procesarEnvio($envio);

    expect($historial)->toHaveCount(3)
        ->and(pedidoResend($historial, 0))->toMatchArray(['metodo' => 'GET', 'ruta' => '/broadcasts/broadcast-1'])
        ->and(pedidoResend($historial, 1))->toMatchArray(['metodo' => 'PATCH', 'ruta' => '/broadcasts/broadcast-1'])
        ->and(pedidoResend($historial, 1)['cuerpo']['html'])->toContain('5')
        ->and(pedidoResend($historial, 1)['cuerpo'])->not->toHaveKey('segment_id')
        ->and(pedidoResend($historial, 2))->toMatchArray(['metodo' => 'POST', 'ruta' => '/broadcasts/broadcast-1/send'])
        ->and($envio->fresh()->estado)->toBe('enviado');
});

it('un reintento no vuelve a mandar el broadcast que ya habia salido', function (): void {
    $historial = resendFalso([respuestaResend(['object' => 'broadcast', 'id' => 'broadcast-1', 'status' => 'sent'])]);
    ['fecha' => $fecha] = fechaConResultados();
    $envio = envioRegistrado($fecha, ['broadcast_id' => 'broadcast-1']);

    procesarEnvio($envio);

    expect($historial)->toHaveCount(1)
        ->and($envio->fresh()->estado)->toBe('enviado');
});

it('si el borrador ya no existe en resend crea otro y lo manda', function (): void {
    $historial = resendFalso([
        respuestaResend(['statusCode' => 404, 'name' => 'not_found', 'message' => 'Broadcast not found'], 404),
        respuestaResend(['id' => 'broadcast-2']),
        respuestaResend(['id' => 'broadcast-2']),
    ]);
    ['fecha' => $fecha] = fechaConResultados();
    $envio = envioRegistrado($fecha, ['broadcast_id' => 'broadcast-borrado']);

    procesarEnvio($envio);

    expect($historial)->toHaveCount(3)
        ->and(pedidoResend($historial, 1))->toMatchArray(['metodo' => 'POST', 'ruta' => '/broadcasts'])
        ->and(pedidoResend($historial, 2))->toMatchArray(['metodo' => 'POST', 'ruta' => '/broadcasts/broadcast-2/send'])
        ->and($envio->fresh()->only(['estado', 'broadcast_id']))->toBe(['estado' => 'enviado', 'broadcast_id' => 'broadcast-2']);
});

it('no registra el envio si nadie eligio recibir el resumen', function (): void {
    Queue::fake();
    ['fecha' => $fecha] = fechaConResultados();
    Suscriptor::factory()->activo()->create(['recibe_resumen_fecha' => false]);

    enviarResumenASuscriptores($this, null, ['idFecha' => $fecha->id])
        ->assertUnprocessable()
        ->assertJson(['success' => false, 'message' => 'Todavía nadie eligió recibir el resumen de la fecha.']);

    expect(EnvioSuscriptores::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('si no se puede encolar queda fallido y se puede volver a pedir', function (): void {
    ['fecha' => $fecha] = fechaConResultados();
    Suscriptor::factory()->activo()->create(['recibe_resumen_fecha' => true]);
    config(['queue.default' => 'cola-inexistente']);

    enviarResumenASuscriptores($this, null, ['idFecha' => $fecha->id])
        ->assertStatus(503)
        ->assertJson(['success' => false, 'message' => 'No se pudo dejar el envío en cola. Probá de nuevo en unos minutos.']);
    expect(EnvioSuscriptores::query()->sole()->estado)->toBe('fallido');

    Queue::fake();
    enviarResumenASuscriptores($this, null, ['idFecha' => $fecha->id])->assertAccepted();
    Queue::assertPushed(EnviarAvisoSuscriptores::class, 1);
});

it('en local el mismo flujo de cola y auditoria entrega el resumen por el mailer solo a los activos que lo eligieron, cada uno con su enlace', function (): void {
    config(['suscripciones.resend.sincronizar' => false]);
    Queue::fake();
    Mail::fake();
    $historial = resendFalso([]);
    ['fecha' => $fecha] = fechaConResultados();
    $elige = Suscriptor::factory()->activo()->create(['email' => 'elige@correo.com', 'recibe_resumen_fecha' => true]);
    Suscriptor::factory()->activo()->create(['email' => 'no-elige@correo.com', 'recibe_resumen_fecha' => false]);
    Suscriptor::factory()->pendiente()->create(['email' => 'pendiente@correo.com', 'recibe_resumen_fecha' => true]);
    Suscriptor::factory()->deBaja()->create(['email' => 'baja@correo.com', 'recibe_resumen_fecha' => true]);
    enviarResumenASuscriptores($this, null, ['idFecha' => $fecha->id])->assertAccepted();

    procesarEnvio($envio = EnvioSuscriptores::query()->sole());

    expect($historial)->toHaveCount(0)
        ->and($envio->fresh()->only(['estado', 'destinatarios']))->toBe(['estado' => 'enviado', 'destinatarios' => 1]);
    Mail::assertSent(ResumenResultadosFechaMail::class, 1);
    Mail::assertSent(ResumenResultadosFechaMail::class, fn (ResumenResultadosFechaMail $correo): bool => $correo->hasTo('elige@correo.com')
        && $correo->urlBajaGeneral === app(SuscripcionService::class)->urlPreferencias($elige));
});

it('rechaza la fecha sin resultados cargados sin registrar nada', function (): void {
    Queue::fake();
    ['fecha' => $fecha] = fechaConResultados(false);

    enviarResumenASuscriptores($this, null, ['idFecha' => $fecha->id])
        ->assertUnprocessable()
        ->assertJson(['success' => false, 'message' => 'No hay resultados para enviar en esa fecha.']);

    expect(EnvioSuscriptores::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('bloquea el segundo envio del mismo resumen, en cola o ya enviado', function (): void {
    Queue::fake();
    resendFalso([respuestaResend(['id' => 'broadcast-1']), respuestaResend(['id' => 'broadcast-1'])]);
    ['fecha' => $fecha] = fechaConResultados();
    Suscriptor::factory()->activo()->create(['recibe_resumen_fecha' => true]);
    $admin = adminEnvioResumen();

    enviarResumenASuscriptores($this, $admin, ['idFecha' => $fecha->id])->assertAccepted();
    enviarResumenASuscriptores($this, $admin, ['idFecha' => $fecha->id])
        ->assertConflict()
        ->assertJson(['success' => false, 'message' => 'Este resumen ya está en cola para enviarse.']);

    $this->travelTo(now()->addDays(3));
    procesarEnvio($envio = EnvioSuscriptores::query()->sole());

    enviarResumenASuscriptores($this, $admin, ['idFecha' => $fecha->id])
        ->assertConflict()
        ->assertJson(['success' => false, 'message' => 'Este resumen ya se envió a los suscriptores el '.$envio->fresh()->enviado_at->format('d/m/Y H:i').'.']);

    Queue::assertPushed(EnviarAvisoSuscriptores::class, 1);
    expect(EnvioSuscriptores::query()->count())->toBe(1);
});

it('si resend falla vuelve a intentar y al agotar los intentos queda fallido sin loguear correos', function (): void {
    Queue::fake();
    Log::spy();
    resendFalso([respuestaResend(['statusCode' => 422, 'name' => 'validation_error', 'message' => 'Invalid from madre@correo.com'], 422)]);
    ['fecha' => $fecha] = fechaConResultados();
    Suscriptor::factory()->activo()->create(['recibe_resumen_fecha' => true]);
    enviarResumenASuscriptores($this, null, ['idFecha' => $fecha->id])->assertAccepted();
    $envio = EnvioSuscriptores::query()->sole();
    $job = (new EnviarAvisoSuscriptores($envio->id))->withFakeQueueInteractions();

    expect(fn () => $job->handle(app(EnvioSuscriptoresService::class)))->toThrow(Exception::class);
    expect($envio->fresh()->estado)->toBe('pendiente')
        ->and($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([60, 300]);

    $job->failed(new RuntimeException('Invalid from madre@correo.com', 422));

    expect($envio->fresh()->estado)->toBe('fallido');
    Log::shouldHaveReceived('error')->once()->withArgs(fn (string $mensaje, array $contexto): bool => $contexto['envio_id'] === $envio->id
        && ! str_contains(json_encode($contexto), 'madre@correo.com'));
});

it('un envio fallido se puede volver a pedir y reutiliza su registro', function (): void {
    Queue::fake();
    ['fecha' => $fecha] = fechaConResultados();
    Suscriptor::factory()->activo()->create(['recibe_resumen_fecha' => true]);
    $admin = adminEnvioResumen();
    enviarResumenASuscriptores($this, null, ['idFecha' => $fecha->id])->assertAccepted();
    EnvioSuscriptores::query()->update(['estado' => 'fallido', 'broadcast_id' => 'broadcast-viejo']);

    enviarResumenASuscriptores($this, $admin, ['idFecha' => $fecha->id])->assertAccepted()->assertJson(['estado' => 'pendiente']);

    $envio = EnvioSuscriptores::query()->sole();
    expect($envio->only(['estado', 'usuario_id', 'broadcast_id']))->toBe(['estado' => 'pendiente', 'usuario_id' => $admin->id, 'broadcast_id' => 'broadcast-viejo']);
    Queue::assertPushed(EnviarAvisoSuscriptores::class, 2);
});

it('si al procesarse la fecha ya no tiene resultados queda fallido sin llamar a resend', function (): void {
    Queue::fake();
    $historial = resendFalso([]);
    ['fecha' => $fecha] = fechaConResultados();
    Suscriptor::factory()->activo()->create(['recibe_resumen_fecha' => true]);
    enviarResumenASuscriptores($this, null, ['idFecha' => $fecha->id])->assertAccepted();
    Partido::query()->update(['golesEquipoLocal' => null, 'golesEquipoVisitante' => null]);

    procesarEnvio($envio = EnvioSuscriptores::query()->sole());

    expect($envio->fresh()->estado)->toBe('fallido')
        ->and($historial)->toHaveCount(0);
});

it('solo un administrador puede enviar el resumen a los suscriptores', function (): void {
    $historial = resendFalso([]);
    ['fecha' => $fecha] = fechaConResultados();
    $usuario = User::factory()->create(['rol' => 'regular', 'email_verified_at' => now()]);

    enviarResumenASuscriptores($this, $usuario, ['idFecha' => $fecha->id])->assertRedirect('/');

    expect($historial)->toHaveCount(0);
});

it('el conteo del resumen informa cuantos suscriptores lo van a recibir', function (): void {
    ['fecha' => $fecha] = fechaConResultados();
    Suscriptor::factory()->activo()->count(2)->create(['recibe_resumen_fecha' => true]);
    Suscriptor::factory()->activo()->create(['recibe_resumen_fecha' => false]);

    $this->actingAs(adminEnvioResumen())
        ->getJson(route('notificaciones.resumen-fecha.contar', ['idFecha' => $fecha->id]))
        ->assertOk()
        ->assertJson(['success' => true, 'destinatarios' => 2, 'partidos_count' => 1, 'envio' => null]);
});

it('el conteo informa si el resumen ya se envio, para no volver a ofrecerlo', function (): void {
    ['fecha' => $fecha] = fechaConResultados();
    EnvioSuscriptores::query()->create([
        'tipo' => 'resumen_fecha', 'clave' => "resumen_fecha:{$fecha->id}:{$fecha->idCategoria}", 'idEdicion' => $fecha->idEdicion,
        'idFecha' => $fecha->id, 'idCategoria' => $fecha->idCategoria, 'estado' => 'enviado', 'destinatarios' => 7,
        'enviado_at' => '2026-10-30 21:15:00',
    ]);

    $this->actingAs(adminEnvioResumen())
        ->getJson(route('notificaciones.resumen-fecha.contar', ['idFecha' => $fecha->id]))
        ->assertOk()
        ->assertJson(['envio' => ['estado' => 'enviado', 'destinatarios' => 7, 'enviado_at' => '30/10/2026 21:15']]);
});

it('la tarjeta del resumen lo envia solo a los suscriptores', function (): void {
    $this->actingAs(adminEnvioResumen())
        ->get(route('notificaciones.index'))
        ->assertOk()
        ->assertSee('id="btn-enviar-resumen-fecha"', false)
        ->assertSee('iniciarEnvioResumenFecha()', false)
        ->assertSee(route('notificaciones.resumen-fecha.suscriptores'), false)
        ->assertDontSee('/Panel/notificaciones/resumen-fecha/enviar-lote', false);
});

it('ya no existe el envio del resumen por bcc a los usuarios', function (): void {
    expect(Route::has('notificaciones.resumen-fecha.enviar-lote'))->toBeFalse();

    $this->actingAs(adminEnvioResumen())
        ->withSession(['_token' => 'token-resumen'])
        ->postJson('/Panel/notificaciones/resumen-fecha/enviar-lote', ['idFecha' => 1, 'offset' => 0, 'limit' => 10], ['X-CSRF-TOKEN' => 'token-resumen'])
        ->assertNotFound();
});

it('el canal del envio depende del entorno: resend en produccion y el mailer en local', function (bool $sincronizar, string $canal): void {
    config(['suscripciones.resend.sincronizar' => $sincronizar]);

    expect(app(CanalEnvioSuscriptores::class))->toBeInstanceOf($canal);
})->with([
    'produccion' => [true, CanalBroadcastResend::class],
    'local con mailhog' => [false, CanalMailerLocal::class],
]);
