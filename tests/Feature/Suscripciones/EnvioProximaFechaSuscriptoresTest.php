<?php

// Envío de la próxima fecha a los suscriptores: los partidos programados (sin resultado) de una
// fecha salen por el mismo canal, auditoría y cola que el resumen, al tópico de la próxima fecha.

use App\Jobs\EnviarAvisoSuscriptores;
use App\Mail\ProximaFechaMail;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\EnvioSuscriptores;
use App\Models\Equipo;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Partido;
use App\Models\Suscriptor;
use App\Models\User;
use App\Services\EnvioSuscriptoresService;
use App\Services\ResumenFechaService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    config([
        'suscripciones.resend.sincronizar' => true,
        'suscripciones.resend.segmento' => 'segmento-sitio',
        'suscripciones.resend.topicos.proxima_fecha' => 'topico-proxima',
        'suscripciones.remitente' => 'avisos@notificaciones.torneosmitai.com',
        'mail.from.name' => 'Torneos Mitai',
    ]);
});

/** @return array{fecha: Fecha, categoria: Categoria} */
function fechaProgramada(bool $conResultado = false): array
{
    $edicion = Edicion::create(['nombre' => 'Edición Próxima']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 10']);
    $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'B']);
    $fecha = Fecha::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Fecha 5']);
    $local = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Los Teros', 'foto' => 't.png']);
    $visitante = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Yaguaretés', 'foto' => 'y.png']);

    Partido::create([
        'idCategoria' => $categoria->id,
        'idFechas' => $fecha->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'idGrupo' => $grupo->id,
        'idEdicion' => $edicion->id,
        'golesEquipoLocal' => $conResultado ? 2 : null,
        'golesEquipoVisitante' => $conResultado ? 0 : null,
        'horario_datetime' => '2026-11-07 10:30:00',
        'cancha' => 2,
        'jugado' => $conResultado ? 1 : 0,
    ]);

    return ['fecha' => $fecha, 'categoria' => $categoria];
}

function adminProximaFecha(): User
{
    return User::factory()->create(['rol' => 'admin', 'email_verified_at' => now(), 'email' => 'admin-proxima@torneomitai.test']);
}

function enviarProximaFecha(object $prueba, User $admin, array $datos): TestResponse
{
    return $prueba->actingAs($admin)
        ->withSession(['_token' => 'token-proxima'])
        ->postJson(route('notificaciones.proxima-fecha.suscriptores'), $datos, ['X-CSRF-TOKEN' => 'token-proxima']);
}

function procesarProximaFecha(EnvioSuscriptores $envio): void
{
    (new EnviarAvisoSuscriptores($envio->id))->withFakeQueueInteractions()->handle(app(EnvioSuscriptoresService::class));
}

it('cuenta los partidos programados y a quienes eligieron la proxima fecha', function (): void {
    ['fecha' => $fecha] = fechaProgramada();
    Suscriptor::factory()->activo()->count(2)->create(['recibe_proxima_fecha' => true]);
    Suscriptor::factory()->activo()->create(['recibe_proxima_fecha' => false]);

    $this->actingAs(adminProximaFecha())
        ->getJson(route('notificaciones.proxima-fecha.contar', ['idFecha' => $fecha->id]))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'destinatarios' => 2,
            'partidos_count' => 1,
            'fecha_nombre' => 'Fecha 5',
            'envio' => null,
        ]);
});

it('no ofrece enviar una fecha sin partidos programados', function (): void {
    Queue::fake();
    ['fecha' => $fecha] = fechaProgramada(conResultado: true);
    Suscriptor::factory()->activo()->create(['recibe_proxima_fecha' => true]);
    $admin = adminProximaFecha();

    $this->actingAs($admin)
        ->getJson(route('notificaciones.proxima-fecha.contar', ['idFecha' => $fecha->id]))
        ->assertUnprocessable()
        ->assertJson(['success' => false, 'message' => 'No hay partidos programados para la fecha seleccionada.']);

    enviarProximaFecha($this, $admin, ['idFecha' => $fecha->id])
        ->assertUnprocessable()
        ->assertJson(['success' => false, 'message' => 'No hay partidos programados para la fecha seleccionada.']);

    expect(EnvioSuscriptores::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('registra la proxima fecha en la auditoria, la encola y no la repite', function (): void {
    Queue::fake();
    ['fecha' => $fecha, 'categoria' => $categoria] = fechaProgramada();
    Suscriptor::factory()->activo()->count(3)->create(['recibe_proxima_fecha' => true]);
    $admin = adminProximaFecha();

    enviarProximaFecha($this, $admin, ['idFecha' => $fecha->id])
        ->assertAccepted()
        ->assertJson([
            'success' => true,
            'estado' => 'pendiente',
            'destinatarios' => 3,
            'message' => 'La próxima fecha quedó en cola para 3 destinatario(s): sale en el próximo minuto.',
        ]);

    $envio = EnvioSuscriptores::query()->sole();
    expect($envio->only(['tipo', 'clave', 'idEdicion', 'idFecha', 'idCategoria', 'usuario_id', 'estado']))->toBe([
        'tipo' => 'proxima_fecha',
        'clave' => "proxima_fecha:{$fecha->id}:{$categoria->id}",
        'idEdicion' => $fecha->idEdicion,
        'idFecha' => $fecha->id,
        'idCategoria' => $categoria->id,
        'usuario_id' => $admin->id,
        'estado' => 'pendiente',
    ]);
    Queue::assertPushed(EnviarAvisoSuscriptores::class, 1);

    enviarProximaFecha($this, $admin, ['idFecha' => $fecha->id])
        ->assertConflict()
        ->assertJson(['success' => false, 'message' => 'Esta próxima fecha ya está en cola para enviarse.']);

    $envio->forceFill(['estado' => 'enviado', 'enviado_at' => '2026-11-01 09:15:00'])->save();

    enviarProximaFecha($this, $admin, ['idFecha' => $fecha->id])
        ->assertConflict()
        ->assertJson(['success' => false, 'message' => 'Esta próxima fecha ya se envió a los suscriptores el 01/11/2026 09:15.']);

    expect(EnvioSuscriptores::query()->count())->toBe(1);
    Queue::assertPushed(EnviarAvisoSuscriptores::class, 1);
});

it('en produccion la manda como broadcast al topico de la proxima fecha con los partidos', function (): void {
    Queue::fake();
    $historial = resendFalso([
        respuestaResend(['id' => 'b-proxima']),
        respuestaResend(['id' => 'b-proxima']),
    ]);
    ['fecha' => $fecha] = fechaProgramada();
    Suscriptor::factory()->activo()->count(2)->create(['recibe_proxima_fecha' => true]);
    enviarProximaFecha($this, adminProximaFecha(), ['idFecha' => $fecha->id])->assertAccepted();

    procesarProximaFecha(EnvioSuscriptores::query()->sole());

    $borrador = pedidoResend($historial, 0);
    expect($borrador['metodo'])->toBe('POST')
        ->and($borrador['ruta'])->toBe('/broadcasts')
        ->and($borrador['cuerpo']['segment_id'])->toBe('segmento-sitio')
        ->and($borrador['cuerpo']['topic_id'])->toBe('topico-proxima')
        ->and($borrador['cuerpo']['subject'])->toBe("Próxima fecha: Fecha 5 (Sub 10) - Torneos Mita'i")
        ->and($borrador['cuerpo']['html'])->toContain('Los Teros')->toContain('Yaguaretés')->toContain('Cancha 2')->toContain('>Sáb 07/11<')->toContain('>10:30<')
        ->and($borrador['cuerpo']['html'])->toContain('src="'.url('fotos/equipos/t.png').'"')->toContain('src="'.url('fotos/equipos/y.png').'"')
        ->and($borrador['cuerpo']['html'])->toContain('href="{{{contact.url_preferencias}}}"')
        ->and($borrador['cuerpo']['text'])->toContain('Los Teros vs Yaguaretés')->toContain('{{{contact.url_preferencias}}}')
        ->and(pedidoResend($historial, 1)['ruta'])->toBe('/broadcasts/b-proxima/send')
        ->and(EnvioSuscriptores::query()->sole()->only(['estado', 'destinatarios', 'broadcast_id']))
        ->toBe(['estado' => 'enviado', 'destinatarios' => 2, 'broadcast_id' => 'b-proxima']);
});

it('en local la entrega por el mailer solo a quienes eligieron la proxima fecha', function (): void {
    config(['suscripciones.resend.sincronizar' => false]);
    Queue::fake();
    Mail::fake();
    ['fecha' => $fecha] = fechaProgramada();
    $elegidos = Suscriptor::factory()->activo()->count(2)->create(['recibe_proxima_fecha' => true]);
    Suscriptor::factory()->activo()->create(['recibe_proxima_fecha' => false]);
    enviarProximaFecha($this, adminProximaFecha(), ['idFecha' => $fecha->id])->assertAccepted();

    procesarProximaFecha(EnvioSuscriptores::query()->sole());

    Mail::assertSent(ProximaFechaMail::class, 2);
    foreach ($elegidos as $suscriptor) {
        Mail::assertSent(ProximaFechaMail::class, fn (ProximaFechaMail $correo): bool => $correo->hasTo($suscriptor->email));
    }
    expect(EnvioSuscriptores::query()->sole()->estado)->toBe('enviado');
});

it('el panel muestra la tarjeta de la proxima fecha junto al resumen', function (): void {
    fechaProgramada();

    $this->actingAs(adminProximaFecha())
        ->get(route('notificaciones.index'))
        ->assertOk()
        ->assertSee('Resumen de la fecha')
        ->assertSee('Próxima fecha')
        ->assertSee('id="btn-enviar-proxima-fecha"', false);
});

it('si al enviarse la fecha ya no tiene partidos programados queda fallida y no manda nada', function (): void {
    config(['suscripciones.resend.sincronizar' => false]);
    Queue::fake();
    Mail::fake();
    ['fecha' => $fecha] = fechaProgramada();
    Suscriptor::factory()->activo()->create(['recibe_proxima_fecha' => true]);
    enviarProximaFecha($this, adminProximaFecha(), ['idFecha' => $fecha->id])->assertAccepted();

    Partido::query()->update(['golesEquipoLocal' => 1, 'golesEquipoVisitante' => 1, 'jugado' => 1]);
    procesarProximaFecha(EnvioSuscriptores::query()->sole());

    Mail::assertNothingSent();
    expect(EnvioSuscriptores::query()->sole()->estado)->toBe('fallido');
});

it('muestra el escudo de cada equipo y, si no tiene, el logo del torneo', function (): void {
    ['fecha' => $fecha] = fechaProgramada();
    Equipo::query()->where('nombre', 'Los Teros')->update(['foto' => 'escudo teros.png']);
    Equipo::query()->where('nombre', 'Yaguaretés')->update(['foto' => null]);

    $proxima = app(ResumenFechaService::class)->armarProximaFecha($fecha->idEdicion, $fecha->id);
    $html = (new ProximaFechaMail($proxima, 'https://torneosmitai.test/preferencias'))->render();

    expect($html)->toContain('src="'.url('fotos/equipos/escudo%20teros.png').'"')
        ->and($html)->toContain('src="'.url('fotos/Logo_Mitai_SinFondo.png').'" alt="Yaguaretés"');
});
