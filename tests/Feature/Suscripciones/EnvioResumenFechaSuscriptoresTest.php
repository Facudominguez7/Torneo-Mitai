<?php

// Envío del resumen de la fecha a los suscriptores: broadcast de Resend o, sin sincronización, el mailer local.

use App\Mail\ResumenResultadosFechaMail;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Partido;
use App\Models\Suscriptor;
use App\Models\User;
use App\Services\SuscripcionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

it('envia el resumen como broadcast de resend al segmento y al topico del resumen', function (): void {
    $historial = resendFalso([respuestaResend(['id' => 'broadcast-1'])]);
    ['fecha' => $fecha] = fechaConResultados();

    $respuesta = enviarResumenASuscriptores($this, null, ['idFecha' => $fecha->id]);

    $respuesta->assertOk()->assertJson(['success' => true, 'canal' => 'resend', 'broadcast_id' => 'broadcast-1']);
    expect($historial)->toHaveCount(1);

    $pedido = pedidoResend($historial, 0);
    expect($pedido['metodo'])->toBe('POST')
        ->and($pedido['ruta'])->toBe('/broadcasts')
        ->and($pedido['cuerpo'])->toMatchArray([
            'segment_id' => 'segmento-sitio',
            'topic_id' => 'topico-resumen',
            'from' => 'Torneos Mitai <avisos@notificaciones.torneosmitai.com>',
            'subject' => "Resultados Fecha 3 - Torneos Mita'i (Sub 12)",
            'send' => true,
        ])
        ->and($pedido['cuerpo']['html'])->toContain('Halcones')->toContain('Pumitas')
        ->toContain('href="{{{contact.url_preferencias}}}"')
        ->and($pedido['cuerpo']['text'])->toContain('{{{contact.url_preferencias}}}');
});

it('sin sincronizacion envia el resumen por el mailer solo a los activos que lo eligieron, cada uno con su enlace', function (): void {
    config(['suscripciones.resend.sincronizar' => false]);
    Mail::fake();
    $historial = resendFalso([]);
    ['fecha' => $fecha] = fechaConResultados();
    $elige = Suscriptor::factory()->activo()->create(['email' => 'elige@correo.com', 'recibe_resumen_fecha' => true]);
    Suscriptor::factory()->activo()->create(['email' => 'no-elige@correo.com', 'recibe_resumen_fecha' => false]);
    Suscriptor::factory()->pendiente()->create(['email' => 'pendiente@correo.com', 'recibe_resumen_fecha' => true]);
    Suscriptor::factory()->deBaja()->create(['email' => 'baja@correo.com', 'recibe_resumen_fecha' => true]);

    enviarResumenASuscriptores($this, null, ['idFecha' => $fecha->id])
        ->assertOk()
        ->assertJson(['success' => true, 'canal' => 'mailer', 'enviados' => 1]);

    expect($historial)->toHaveCount(0);
    Mail::assertSent(ResumenResultadosFechaMail::class, 1);
    Mail::assertSent(ResumenResultadosFechaMail::class, fn (ResumenResultadosFechaMail $correo): bool => $correo->hasTo('elige@correo.com')
        && $correo->urlBajaGeneral === app(SuscripcionService::class)->urlPreferencias($elige));
});

it('rechaza la fecha sin resultados cargados sin llamar a resend', function (): void {
    $historial = resendFalso([]);
    ['fecha' => $fecha] = fechaConResultados(false);

    enviarResumenASuscriptores($this, null, ['idFecha' => $fecha->id])
        ->assertUnprocessable()
        ->assertJson(['success' => false, 'message' => 'No hay resultados para enviar en esa fecha.']);

    expect($historial)->toHaveCount(0);
});

it('no repite el envio de la misma fecha durante diez minutos', function (): void {
    $historial = resendFalso([respuestaResend(['id' => 'broadcast-1'])]);
    ['fecha' => $fecha] = fechaConResultados();
    $admin = adminEnvioResumen();

    enviarResumenASuscriptores($this, $admin, ['idFecha' => $fecha->id])->assertOk();
    enviarResumenASuscriptores($this, $admin, ['idFecha' => $fecha->id])
        ->assertStatus(409)
        ->assertJson(['success' => false, 'message' => 'Este resumen ya se envió a los suscriptores hace menos de 10 minutos.']);

    expect($historial)->toHaveCount(1);
});

it('si resend falla responde el error, no loguea correos y permite reintentar', function (): void {
    Log::spy();
    $historial = resendFalso([
        respuestaResend(['statusCode' => 422, 'name' => 'validation_error', 'message' => 'Invalid from'], 422),
        respuestaResend(['id' => 'broadcast-2']),
    ]);
    ['fecha' => $fecha] = fechaConResultados();
    $admin = adminEnvioResumen();

    enviarResumenASuscriptores($this, $admin, ['idFecha' => $fecha->id])
        ->assertStatus(502)
        ->assertJson(['success' => false, 'message' => 'Resend no aceptó el envío. Probá de nuevo en unos minutos.']);
    Log::shouldHaveReceived('error')->once()->withArgs(fn (string $mensaje, array $contexto): bool => ! array_key_exists('error_mensaje', $contexto));

    enviarResumenASuscriptores($this, $admin, ['idFecha' => $fecha->id])->assertOk()->assertJson(['broadcast_id' => 'broadcast-2']);
    expect($historial)->toHaveCount(2);
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
        ->assertJson(['success' => true, 'suscriptores' => 2, 'partidos_count' => 1]);
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
