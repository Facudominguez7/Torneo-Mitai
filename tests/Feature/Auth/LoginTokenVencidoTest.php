<?php

// Un token CSRF vencido en el login (doble clic en "Acceder" o pestaña abierta demasiado tiempo) no muestra el 419 genérico.

use App\Models\User;

beforeEach(function (): void {
    // Con APP_ENV=testing Laravel no verifica CSRF; se fuerza para reproducir el token vencido.
    app()['env'] = 'local';
    $this->withSession(['_token' => 'token-de-la-sesion']);
});

it('vuelve al login con el email cargado y un aviso cuando el token vencio', function (): void {
    $respuesta = $this->post('/login', ['_token' => 'token-viejo', 'email' => 'socio@example.com', 'password' => 'secreta']);

    $respuesta->assertRedirect(route('login'))
        ->assertSessionHas('aviso', 'La sesión expiró. Volvé a intentar.')
        ->assertSessionHasInput('email', 'socio@example.com')
        ->assertSessionMissing('_old_input.password');
    $this->assertGuest();
});

it('lleva al destino del login cuando el usuario ya habia ingresado', function (): void {
    $usuario = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($usuario)
        ->post('/login', ['_token' => 'token-viejo', 'email' => $usuario->email, 'password' => 'password'])
        ->assertRedirect(route('home', absolute: false));
    $this->assertAuthenticatedAs($usuario);
});

it('sigue respondiendo 419 en otros formularios con el token vencido', function (): void {
    $this->post('/register', ['_token' => 'token-viejo', 'email' => 'socio@example.com'])->assertStatus(419);
});

it('responde 419 en JSON cuando el login se pide por API con el token vencido', function (): void {
    $this->postJson('/login', ['_token' => 'token-viejo', 'email' => 'socio@example.com', 'password' => 'secreta'])
        ->assertStatus(419)
        ->assertJsonStructure(['message']);
});

it('muestra el aviso de sesion expirada en el login', function (): void {
    $this->withSession(['aviso' => 'La sesión expiró. Volvé a intentar.'])->get('/login')
        ->assertOk()
        ->assertSeeText('La sesión expiró. Volvé a intentar.');
});

it('deshabilita el boton Acceder al enviar el formulario', function (): void {
    $this->get('/login')->assertOk()
        ->assertSee('x-on:submit="enviando = true"', false)
        ->assertSee('x-bind:disabled="enviando"', false);
});
