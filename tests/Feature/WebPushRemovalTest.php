<?php

use App\Models\User;
use App\Notifications\NotificacionPersonalizada;
use App\Notifications\NuevoPartidoProgramado;
use App\Notifications\ResultadoCargado;
use Illuminate\Support\Facades\Route;

it('removes the Web Push UI and routes while keeping email notifications', function (): void {
    $response = $this->get('/');

    $response->assertSuccessful()->assertDontSee('push-toggle-btn');

    foreach (Route::getRoutes()->getRoutes() as $route) {
        expect($route->uri())->not->toContain('push');
    }

    expect(array_filter(Route::getRoutes()->getRoutes(), fn ($route): bool => str_starts_with((string) $route->getName(), 'push.')))
        ->toBeEmpty();

    expect(route('email.estado', absolute: false))->toBe('/email/estado');
});

it('requires authentication for the email subscription status endpoint', function (): void {
    $this->getJson('/email/estado')->assertUnauthorized();
    $this->get('/Panel/notificaciones')->assertRedirect('/login');
});

it('returns the authenticated user email subscription status', function (): void {
    $user = User::factory()->create([
        'email' => 'status@gmail.com',
        'email_subscribed' => true,
        'email_verified_at' => now(),
        'requires_email_verification_for_notifications' => false,
    ]);

    $this->actingAs($user)
        ->getJson('/email/estado')
        ->assertOk()
        ->assertExactJson([
            'email_subscribed' => true,
            'email_verified' => true,
            'puede_recibir_emails' => true,
        ]);
});

it('keeps the tournament notifications on the email channel only', function (): void {
    $user = User::factory()->create([
        'email' => 'notifications@gmail.com',
        'email_subscribed' => true,
        'requires_email_verification_for_notifications' => false,
    ]);

    expect((new NuevoPartidoProgramado((object) []))->via($user))->toBe(['mail'])
        ->and((new ResultadoCargado((object) []))->via($user))->toBe(['mail'])
        ->and((new NotificacionPersonalizada('Title', 'Message'))->via($user))->toBe(['mail']);
});

it('keeps the PWA service worker cache and offline handlers without push handlers', function (): void {
    $serviceWorker = file_get_contents(public_path('sw.js'));

    expect($serviceWorker)->toContain("addEventListener('install'")
        ->toContain("addEventListener('fetch'")
        ->not->toContain("addEventListener('push'")
        ->not->toContain("addEventListener('notificationclick'");
});
