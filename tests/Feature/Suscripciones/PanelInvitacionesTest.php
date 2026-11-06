<?php

// Sección "Invitaciones" del panel de suscriptores (T3, S2): cómo vienen las invitaciones diarias
// a sumarse a los avisos por correo y cuántos usuarios faltan invitar. La tasa de rebote se mide
// sobre los invitados y se marca cuando llega al 4 % que admite Resend.

use App\Models\Suscriptor;
use App\Models\User;

function adminInvitaciones(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
        'name' => 'Admin Invitaciones',
        'email' => 'admin.invitaciones@example.com',
        'email_subscribed' => false,
    ]);
}

function candidatoAInvitacion(string $email): User
{
    return User::factory()->create(['email' => $email, 'email_subscribed' => true]);
}

it('muestra cómo vienen las invitaciones con los conteos exactos y la tasa de rebote', function (): void {
    $this->freezeTime();
    candidatoAInvitacion('falta-uno@correo.com');
    candidatoAInvitacion('falta-dos@correo.com');

    // Dos invitados que ya confirmaron.
    Suscriptor::factory()->create(['email' => 'confirmada-uno@correo.com', 'invitado_at' => now()->subDays(5), 'confirmado_at' => now()->subDays(4)]);
    Suscriptor::factory()->create(['email' => 'confirmada-dos@correo.com', 'invitado_at' => now()->subHours(2), 'confirmado_at' => now()->subHour()]);
    // Dos pendientes con el enlace todavía vigente (72 h).
    Suscriptor::factory()->create(['email' => 'pendiente-uno@correo.com', 'invitado_at' => now()->subHours(10)]);
    Suscriptor::factory()->create(['email' => 'pendiente-dos@correo.com', 'invitado_at' => now()->subHours(30), 'rebote_at' => now()->subHours(29)]);
    // Dos vencidos sin confirmar.
    Suscriptor::factory()->create(['email' => 'vencida-uno@correo.com', 'invitado_at' => now()->subDays(10)]);
    Suscriptor::factory()->create(['email' => 'vencida-dos@correo.com', 'invitado_at' => now()->subDays(20), 'queja_at' => now()->subDays(19)]);
    // Se suscribió desde el sitio: no es una invitación y no se cuenta.
    Suscriptor::factory()->activo()->create(['email' => 'del-sitio@correo.com']);

    $html = $this->actingAs(adminInvitaciones())->get(route('notificaciones.index'))->assertOk()->getContent();

    expect($html)
        ->toContain('Invitaciones')
        ->toContain('data-estadistica="invitados">6<')
        ->toContain('data-estadistica="confirmados">2<')
        ->toContain('data-estadistica="pendientes">2<')
        ->toContain('data-estadistica="vencidos">2<')
        ->toContain('data-estadistica="rebotes">1<')
        ->toContain('data-estadistica="quejas">1<')
        ->toContain('data-estadistica="tasa-rebote">16,7 %<')
        ->toContain('data-estadistica="faltan-invitar">2<')
        ->toContain('todos los días a las 10:00, hasta 50 invitaciones');
});

it('sin invitados muestra un guion en la tasa de rebote y no divide por cero', function (): void {
    $this->freezeTime();

    $html = $this->actingAs(adminInvitaciones())->get(route('notificaciones.index'))->assertOk()->getContent();

    expect($html)
        ->toContain('data-estadistica="invitados">0<')
        ->toContain('data-estadistica="tasa-rebote">—<')
        ->not->toContain('Supera el 4 % de Resend');
});

it('avisa cuando la tasa de rebote llega al 4 % que admite Resend', function (): void {
    $this->freezeTime();
    Suscriptor::factory()->count(24)->create(['invitado_at' => now()->subDays(3)]);
    Suscriptor::factory()->create(['invitado_at' => now()->subDays(3), 'rebote_at' => now()->subDays(2)]);

    $html = $this->actingAs(adminInvitaciones())->get(route('notificaciones.index'))->assertOk()->getContent();

    expect($html)
        ->toContain('data-estadistica="invitados">25<')
        ->toContain('data-estadistica="tasa-rebote">4,0 %<')
        ->toContain('Supera el 4 % de Resend')
        ->toContain('por encima de esa tasa pueden suspenderse los envíos');
});

it('con la tasa de rebote por debajo del 4 % no avisa', function (): void {
    $this->freezeTime();
    Suscriptor::factory()->count(49)->create(['invitado_at' => now()->subDays(3)]);
    Suscriptor::factory()->create(['invitado_at' => now()->subDays(3), 'rebote_at' => now()->subDays(2)]);

    $html = $this->actingAs(adminInvitaciones())->get(route('notificaciones.index'))->assertOk()->getContent();

    expect($html)
        ->toContain('data-estadistica="tasa-rebote">2,0 %<')
        ->not->toContain('Supera el 4 % de Resend');
});

it('cuando no queda nadie por invitar lo dice en lugar de anunciar el lote', function (): void {
    $this->freezeTime();

    $html = $this->actingAs(adminInvitaciones())->get(route('notificaciones.index'))->assertOk()->getContent();

    expect($html)
        ->toContain('data-estadistica="faltan-invitar">0<')
        ->toContain('no queda nadie por invitar')
        ->not->toContain('hasta 50 invitaciones');
});

it('no muestra correos de los invitados en la sección', function (): void {
    $this->freezeTime();
    Suscriptor::factory()->create(['email' => 'persona.invitada@correo.com', 'invitado_at' => now()->subHours(3)]);
    Suscriptor::factory()->create(['email' => 'persona.rebotada@correo.com', 'invitado_at' => now()->subDays(9), 'rebote_at' => now()->subDays(8)]);

    $this->actingAs(adminInvitaciones())
        ->get(route('notificaciones.index'))
        ->assertOk()
        ->assertDontSee('persona.invitada@correo.com')
        ->assertDontSee('persona.rebotada@correo.com');
});

it('ubica la sección antes de las tarjetas de envío y deja el resto de la página igual', function (): void {
    $this->freezeTime();

    $html = $this->actingAs(adminInvitaciones())->get(route('notificaciones.index'))->assertOk()->getContent();

    expect(strpos($html, 'data-estadistica="invitados"'))->toBeLessThan(strpos($html, 'Resumen de la fecha'))
        ->and($html)
        ->toContain('Próxima fecha')
        ->toContain('Aviso del torneo')
        ->toContain('id="btn-enviar-resumen-fecha"');
});

it('un usuario que no es admin no ve la sección', function (): void {
    $usuario = User::factory()->create(['rol' => 'regular', 'email_subscribed' => false]);

    $this->actingAs($usuario)
        ->get(route('notificaciones.index'))
        ->assertRedirect('/');
});
