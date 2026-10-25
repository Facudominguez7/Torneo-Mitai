<?php

// Suscripción con solo un email: doble opt-in, equipos seguidos y baja con enlace firmado.

use App\Mail\ConfirmacionSuscripcionMail;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Suscriptor;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Mail::fake();
});

function suscripcionEquipo(string $nombre = 'Huracán'): Equipo
{
    $edicion = Edicion::create(['nombre' => '21° Edición Torneos Mita\'í']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Categoría 2015']);

    return Equipo::create(['nombre' => $nombre, 'idCategoria' => $categoria->id, 'idEdicion' => $edicion->id]);
}

/** @param  array<string, mixed>  $datos */
function suscribirse(array $datos): TestResponse
{
    return test()->withSession(['_token' => 'token-suscripcion'])
        ->from('/')
        ->post(route('suscripcion.store'), ['_token' => 'token-suscripcion', ...$datos]);
}

it('guarda la suscripcion pendiente y manda el correo de confirmacion', function (): void {
    $equipo = suscripcionEquipo();

    suscribirse(['email' => '  Madre.Huracan@Correo.com ', 'equipos' => [$equipo->id]])
        ->assertRedirect('/')
        ->assertSessionHas('suscripcion', 'Te enviamos un correo a madre.huracan@correo.com. Abrilo y confirmá la suscripción.');

    $suscriptor = Suscriptor::query()->sole();
    expect($suscriptor->email)->toBe('madre.huracan@correo.com')
        ->and($suscriptor->confirmado_at)->toBeNull()
        ->and($suscriptor->estaActivo())->toBeFalse()
        ->and($suscriptor->recibe_resumen_fecha)->toBeTrue()
        ->and($suscriptor->recibe_avisos)->toBeTrue()
        ->and($suscriptor->recibe_proxima_fecha)->toBeTrue()
        ->and($suscriptor->equipos->pluck('id')->all())->toBe([$equipo->id]);

    Mail::assertSent(ConfirmacionSuscripcionMail::class, function (ConfirmacionSuscripcionMail $mail) use ($suscriptor): bool {
        return $mail->hasTo('madre.huracan@correo.com')
            && $mail->mailer === 'resend'
            && str_contains($mail->urlConfirmacion, route('suscripcion.confirmar', $suscriptor, false))
            && str_contains($mail->urlConfirmacion, 'signature=');
    });
});

it('guarda los avisos que eligio', function (): void {
    suscribirse(['email' => 'abuelo@correo.com', 'avisos' => ['resumen_fecha']]);

    $suscriptor = Suscriptor::query()->sole();
    expect($suscriptor->recibe_resumen_fecha)->toBeTrue()
        ->and($suscriptor->recibe_avisos)->toBeFalse()
        ->and($suscriptor->recibe_proxima_fecha)->toBeFalse();
});

it('confirma la suscripcion con el enlace firmado', function (): void {
    $suscriptor = Suscriptor::factory()->pendiente()->create();

    $this->get(URL::temporarySignedRoute('suscripcion.confirmar', now()->addDay(), $suscriptor))
        ->assertRedirect('/')
        ->assertSessionHas('suscripcion', '¡Listo! Ya estás suscripto a los avisos de Torneos Mita\'í.');

    expect($suscriptor->fresh()->estaActivo())->toBeTrue();
});

it('rechaza un enlace de confirmacion sin firma o vencido', function (): void {
    $suscriptor = Suscriptor::factory()->pendiente()->create();
    $vencido = URL::temporarySignedRoute('suscripcion.confirmar', now()->addDay(), $suscriptor);

    $this->get(route('suscripcion.confirmar', $suscriptor))->assertForbidden();
    $this->travel(2)->days();
    $this->get($vencido)->assertForbidden();

    expect($suscriptor->fresh()->confirmado_at)->toBeNull();
});

it('da de baja con el enlace firmado aunque llegue sin token csrf', function (): void {
    app()['env'] = 'local';
    $suscriptor = Suscriptor::factory()->activo()->create();

    $this->post(URL::signedRoute('suscripcion.baja', $suscriptor))
        ->assertRedirect('/')
        ->assertSessionHas('suscripcion', 'Te diste de baja. No vas a recibir más avisos por correo.');

    expect($suscriptor->fresh()->baja_at)->not->toBeNull()
        ->and($suscriptor->fresh()->estaActivo())->toBeFalse();
});

it('no da de baja sin la firma del enlace', function (): void {
    $suscriptor = Suscriptor::factory()->activo()->create();

    $this->post(route('suscripcion.baja', $suscriptor))->assertForbidden();

    expect($suscriptor->fresh()->estaActivo())->toBeTrue();
});

it('no cambia las preferencias de un suscriptor activo desde el formulario publico', function (): void {
    $equipo = suscripcionEquipo();
    $suscriptor = Suscriptor::factory()->activo()->create(['email' => 'padre@correo.com']);

    suscribirse(['email' => 'padre@correo.com', 'equipos' => [$equipo->id], 'avisos' => []])
        ->assertSessionHas('suscripcion', 'Te enviamos un correo a padre@correo.com. Abrilo y confirmá la suscripción.');

    $suscriptor->refresh();
    expect($suscriptor->estaActivo())->toBeTrue()
        ->and($suscriptor->recibe_avisos)->toBeTrue()
        ->and($suscriptor->equipos)->toBeEmpty()
        ->and(Suscriptor::query()->count())->toBe(1);
    Mail::assertNothingSent();
});

it('vuelve a pedir confirmacion a quien se habia dado de baja', function (): void {
    $suscriptor = Suscriptor::factory()->deBaja()->create(['email' => 'tia@correo.com']);

    suscribirse(['email' => 'tia@correo.com']);

    $suscriptor->refresh();
    expect($suscriptor->baja_at)->toBeNull()
        ->and($suscriptor->confirmado_at)->toBeNull();
    Mail::assertSent(ConfirmacionSuscripcionMail::class, fn (ConfirmacionSuscripcionMail $mail): bool => $mail->hasTo('tia@correo.com'));
});

it('suscribe sin confirmacion al usuario que ingreso con su email verificado', function (): void {
    $usuario = User::factory()->create(['email' => 'socio@correo.com', 'email_verified_at' => now()]);

    $this->actingAs($usuario);
    suscribirse(['email' => 'Socio@correo.com'])
        ->assertSessionHas('suscripcion', '¡Listo! Ya estás suscripto a los avisos de Torneos Mita\'í.');

    $suscriptor = Suscriptor::query()->sole();
    expect($suscriptor->estaActivo())->toBeTrue()
        ->and($suscriptor->usuario_id)->toBe($usuario->id);
    Mail::assertNothingSent();
});

it('valida el correo y los equipos', function (array $datos, string $campo): void {
    suscribirse($datos)->assertSessionHasErrors($campo);

    expect(Suscriptor::query()->count())->toBe(0);
    Mail::assertNothingSent();
})->with([
    'sin email' => [['email' => ''], 'email'],
    'email invalido' => [['email' => 'no-es-un-correo'], 'email'],
    'dominio bloqueado' => [['email' => 'prueba@example.com'], 'email'],
    'equipo inexistente' => [['email' => 'a@correo.com', 'equipos' => [999999]], 'equipos.0'],
    'aviso desconocido' => [['email' => 'a@correo.com', 'avisos' => ['spam']], 'avisos.0'],
]);

it('limita la cantidad de equipos seguidos', function (): void {
    $equipos = collect(range(1, 6))->map(fn (int $numero): int => suscripcionEquipo("Equipo {$numero}")->id)->all();

    suscribirse(['email' => 'a@correo.com', 'equipos' => $equipos])->assertSessionHasErrors('equipos');
});
