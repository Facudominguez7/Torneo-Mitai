<?php

// Migración de los usuarios que aceptaban correos con su cuenta a la lista de suscriptores:
// entran confirmados, con los tres avisos, sin doble opt-in y sin tocar a los que ya estaban.

use App\Jobs\SincronizarSuscriptorResend;
use App\Models\Suscriptor;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

/** @param  array<string, mixed>  $atributos */
function usuarioConCorreos(string $email, array $atributos = []): User
{
    return User::factory()->create(['email' => $email, 'email_subscribed' => true, 'email_verified_at' => now(), ...$atributos]);
}

it('en modo prueba informa los conteos y no escribe nada', function (): void {
    Queue::fake();
    usuarioConCorreos('nuevo@correo.com');
    usuarioConCorreos('ya@correo.com');
    usuarioConCorreos('bloqueado@mail.ru');
    Suscriptor::factory()->activo()->create(['email' => 'ya@correo.com']);

    $this->artisan('suscripciones:migrar-usuarios', ['--dry-run' => true])
        ->expectsOutputToContain('Modo prueba: no se guardó ningún cambio.')
        ->expectsOutputToContain('Candidatos: 3')
        ->expectsOutputToContain('Se crearían: 1')
        ->expectsOutputToContain('Omitidos porque el correo ya está en la lista: 1')
        ->expectsOutputToContain('Omitidos por dominio bloqueado: 1')
        ->assertSuccessful();

    expect(Suscriptor::query()->pluck('email')->all())->toBe(['ya@correo.com']);
    Queue::assertNothingPushed();
});

it('copia a los usuarios verificados que aceptaban correos como suscriptores confirmados con los tres avisos', function (): void {
    Queue::fake();
    $usuario = usuarioConCorreos('  Madre.Halcones@Correo.com ');

    $this->artisan('suscripciones:migrar-usuarios')
        ->expectsOutputToContain('Candidatos: 1')
        ->expectsOutputToContain('Creados: 1')
        ->expectsOutputToContain('Omitidos porque el correo ya está en la lista: 0')
        ->expectsOutputToContain('Omitidos por dominio bloqueado: 0')
        ->assertSuccessful();

    $suscriptor = Suscriptor::query()->sole();
    expect($suscriptor->only(['email', 'usuario_id', 'recibe_resumen_fecha', 'recibe_avisos', 'recibe_proxima_fecha']))->toBe([
        'email' => 'madre.halcones@correo.com',
        'usuario_id' => $usuario->id,
        'recibe_resumen_fecha' => true,
        'recibe_avisos' => true,
        'recibe_proxima_fecha' => true,
    ])
        ->and($suscriptor->estaActivo())->toBeTrue()
        ->and($suscriptor->resend_sincronizado_at)->toBeNull()
        ->and($suscriptor->cambio_local_ms)->not->toBeNull();
});

it('no migra a los usuarios sin verificar, a los que no aceptaban correos ni a los de dominios bloqueados', function (): void {
    Queue::fake();
    usuarioConCorreos('sin-verificar@correo.com', ['email_verified_at' => null]);
    usuarioConCorreos('no-acepta@correo.com', ['email_subscribed' => false]);
    usuarioConCorreos('bloqueado@mail.ru');

    $this->artisan('suscripciones:migrar-usuarios')
        ->expectsOutputToContain('Candidatos: 1')
        ->expectsOutputToContain('Creados: 0')
        ->expectsOutputToContain('Omitidos por dominio bloqueado: 1')
        ->assertSuccessful();

    expect(Suscriptor::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('no cambia al suscriptor que ya existe con ese correo, aunque este de baja o con otras mayusculas', function (): void {
    Queue::fake();
    $deBaja = Suscriptor::factory()->deBaja()->create(['email' => 'baja@correo.com', 'recibe_avisos' => false]);
    $activo = Suscriptor::factory()->activo()->create(['email' => 'activo@correo.com', 'recibe_proxima_fecha' => false]);
    usuarioConCorreos('baja@correo.com');
    usuarioConCorreos('ACTIVO@Correo.com');
    $antes = Suscriptor::query()->orderBy('id')->get()->map->getAttributes()->all();

    $this->artisan('suscripciones:migrar-usuarios')
        ->expectsOutputToContain('Candidatos: 2')
        ->expectsOutputToContain('Creados: 0')
        ->expectsOutputToContain('Omitidos porque el correo ya está en la lista: 2')
        ->assertSuccessful();

    expect(Suscriptor::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($antes)
        ->and($deBaja->fresh()->baja_at)->not->toBeNull()
        ->and($activo->fresh()->usuario_id)->toBeNull();
    Queue::assertNothingPushed();
});

it('correrlo dos veces no crea nada nuevo', function (): void {
    Queue::fake();
    usuarioConCorreos('uno@correo.com');
    usuarioConCorreos('dos@correo.com');

    $this->artisan('suscripciones:migrar-usuarios')->expectsOutputToContain('Creados: 2')->assertSuccessful();
    $this->artisan('suscripciones:migrar-usuarios')
        ->expectsOutputToContain('Creados: 0')
        ->expectsOutputToContain('Omitidos porque el correo ya está en la lista: 2')
        ->assertSuccessful();

    expect(Suscriptor::query()->count())->toBe(2);
    Queue::assertPushed(SincronizarSuscriptorResend::class, 2);
});

it('encola una sincronizacion con resend por cada suscriptor creado', function (): void {
    Queue::fake();
    usuarioConCorreos('uno@correo.com');
    usuarioConCorreos('dos@correo.com');

    $this->artisan('suscripciones:migrar-usuarios')->assertSuccessful();

    $ids = Suscriptor::query()->orderBy('id')->pluck('id')->all();
    Queue::assertPushed(SincronizarSuscriptorResend::class, 2);
    foreach ($ids as $id) {
        Queue::assertPushed(SincronizarSuscriptorResend::class, fn (SincronizarSuscriptorResend $job): bool => $job->suscriptorId === $id);
    }
});

it('si falla el alta de un usuario no deja una fila a medias, sigue con los demas y loguea solo el dominio', function (): void {
    Queue::fake();
    Log::spy();
    usuarioConCorreos('falla@roto.com');
    usuarioConCorreos('bien@correo.com');
    Suscriptor::saving(function (Suscriptor $suscriptor): void {
        if ($suscriptor->email === 'falla@roto.com') {
            throw new RuntimeException('falla@roto.com no se pudo guardar');
        }
    });

    $this->artisan('suscripciones:migrar-usuarios')
        ->expectsOutputToContain('Creados: 1')
        ->expectsOutputToContain('Con error: 1')
        ->assertFailed();

    expect(Suscriptor::query()->pluck('email')->all())->toBe(['bien@correo.com']);
    Queue::assertPushed(SincronizarSuscriptorResend::class, 1);
    Log::shouldHaveReceived('error')->once()->withArgs(fn (string $mensaje, array $contexto): bool => $contexto['dominio'] === 'roto.com'
        && ! str_contains(json_encode($contexto), 'falla@'));
});
