<?php

// Registrarse exige aceptar los Términos y condiciones y la Política de privacidad, y se guarda cuándo se aceptaron.

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as UsuarioGoogle;

beforeEach(function (): void {
    Notification::fake();
    $this->withSession(['_token' => 'aceptacion-terminos']);
});

function datosRegistro(array $extra = []): array
{
    return [
        '_token' => 'aceptacion-terminos',
        'name' => 'Socia Mitai',
        'email' => 'socia@gmail.com',
        'password' => 'clave-segura-123',
        'password_confirmation' => 'clave-segura-123',
    ] + $extra;
}

function simularGoogle(string $email): void
{
    $usuario = (new UsuarioGoogle)->map(['id' => 'google-123', 'name' => 'Socio Google', 'email' => $email, 'avatar' => 'https://example.org/avatar.png']);
    Socialite::shouldReceive('driver->stateless->user')->andReturn($usuario);
}

it('no registra la cuenta si no se aceptan los terminos', function (): void {
    $this->post('/register', datosRegistro())
        ->assertSessionHasErrors(['terminos' => 'Tenés que aceptar los Términos y condiciones y la Política de privacidad.']);

    expect(User::query()->where('email', 'socia@gmail.com')->exists())->toBeFalse();
    $this->assertGuest();
});

it('guarda cuando se aceptaron los terminos al registrarse', function (): void {
    $this->travelTo(now()->setDateTime(2026, 10, 25, 11, 30));

    $this->post('/register', datosRegistro(['terminos' => '1']))->assertRedirect(route('verification.notice'));

    expect(User::query()->where('email', 'socia@gmail.com')->firstOrFail()->terminos_aceptados_at?->toDateTimeString())
        ->toBe('2026-10-25 11:30:00');
});

it('muestra el checkbox obligatorio con los enlaces legales en el registro', function (): void {
    $this->get('/register')->assertOk()
        ->assertSee('name="terminos"', false)
        ->assertSeeText('Leí y acepto los')
        ->assertSee(route('terminos'), false)
        ->assertSee(route('privacidad'), false)
        ->assertSeeText('Si te registrás con Google, también aceptás los Términos y condiciones y la Política de privacidad.');
});

it('registra la aceptacion al crear la cuenta con Google', function (): void {
    $this->travelTo(now()->setDateTime(2026, 10, 25, 12, 0));
    simularGoogle('nuevo@gmail.com');

    $this->get('/auth/google/callback')->assertRedirect();

    expect(User::query()->where('email', 'nuevo@gmail.com')->firstOrFail()->terminos_aceptados_at?->toDateTimeString())
        ->toBe('2026-10-25 12:00:00');
});

it('no cambia la aceptacion de una cuenta de Google que ya existia', function (): void {
    $usuario = User::factory()->create(['email' => 'existente@gmail.com', 'google_id' => 'google-123']);
    simularGoogle('existente@gmail.com');

    $this->get('/auth/google/callback')->assertRedirect();

    $this->assertAuthenticatedAs($usuario);
    expect($usuario->fresh()->terminos_aceptados_at)->toBeNull();
});

it('no permite que un formulario escriba la fecha de aceptacion', function (): void {
    expect((new User)->isFillable('terminos_aceptados_at'))->toBeFalse();
});
