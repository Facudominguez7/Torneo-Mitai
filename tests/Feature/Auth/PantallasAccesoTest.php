<?php

// Las pantallas de acceso usan el diseño "Cancha nocturna" y conservan sus formularios y los enlaces legales.

use App\Models\User;

it('renderiza cada pantalla de invitado con su formulario y los enlaces legales', function (string $url, string $titulo, string $accion): void {
    $this->get($url)->assertOk()
        ->assertSeeText($titulo)
        ->assertSee('action="'.route($accion).'"', false)
        ->assertSee('bg-brand-navy-deep', false)
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
