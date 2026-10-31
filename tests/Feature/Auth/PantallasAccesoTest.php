<?php

// Las pantallas de acceso usan el diseño "Medio campo" y conservan sus formularios y los enlaces legales.

use App\Models\User;

it('renderiza cada pantalla de invitado con su formulario y los enlaces legales', function (string $url, string $titulo, string $accion): void {
    $this->get($url)->assertOk()
        ->assertSeeText($titulo)
        ->assertSee('action="'.route($accion).'"', false)
        ->assertSee('class="acceso-cancha', false)
        ->assertSee('class="acceso-formulario', false)
        ->assertSee(route('terminos'), false)
        ->assertSee(route('privacidad'), false);
})->with([
    'login' => ['/login', 'Ingresá a tu cuenta', 'login'],
    'registro' => ['/register', 'Creá tu cuenta', 'register'],
    'olvidé mi contraseña' => ['/forgot-password', '¿Olvidaste tu contraseña?', 'password.email'],
    'restablecer contraseña' => ['/reset-password/token-de-prueba?email=socio@example.com', 'Elegí tu contraseña nueva', 'password.store'],
]);

it('pide restablecer la contrasena cuando el acceso lo exige', function (): void {
    $this->get('/forgot-password/Si')->assertOk()->assertSeeText('Restablecé tu contraseña');
});

it('renderiza confirmar contrasena y verificar correo para un usuario logueado', function (): void {
    $usuario = User::factory()->create(['email_verified_at' => null, 'requires_email_verification_for_notifications' => true]);

    $this->actingAs($usuario)->get('/confirm-password')->assertOk()->assertSeeText('Confirmá tu contraseña');
    $this->actingAs($usuario)->get('/verify-email')->assertOk()
        ->assertSeeText('Verificá tu correo')
        ->assertSeeText($usuario->email);
});

it('ofrece mostrar la contrasena en el login', function (): void {
    $this->get('/login')->assertOk()
        ->assertSee('aria-label="Mostrar contraseña"', false)
        ->assertSee("x-bind:type=\"visible ? 'text' : 'password'\"", false);
});

it('muestra en la cancha el titular propio de cada pantalla', function (string $url, string $sobre, string $titular): void {
    $this->get($url)->assertOk()
        ->assertSeeText($sobre)
        ->assertSeeText($titular)
        ->assertSee('Logo_Mitai_SinFondo.png', false);
})->with([
    'login' => ['/login', 'Fecha a fecha', 'Todo lo de tu equipo'],
    'registro' => ['/register', 'Nueva cuenta', 'Seguí a tu equipo fecha a fecha.'],
    'olvidé mi contraseña' => ['/forgot-password', 'Recuperar acceso', 'Volvé a la cancha en un minuto.'],
]);

it('mantiene el registro con la aceptacion de terminos y el ingreso con google', function (): void {
    $this->get('/register')->assertOk()
        ->assertSee('name="terminos"', false)
        ->assertSee('href="/auth/redirect"', false)
        ->assertSeeText('Registrarme con Google')
        ->assertSeeText('Si te registrás con Google, también aceptás los Términos y condiciones y la Política de privacidad.');
});

it('ofrece volver a ingresar desde olvidé mi contraseña', function (): void {
    $this->get('/forgot-password')->assertOk()
        ->assertSee('href="'.route('login').'"', false)
        ->assertSeeText('Volver a ingresar');
});
