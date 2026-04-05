<?php

use App\Models\User;
use App\Services\EnlaceBajaEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('permite darse de baja con un enlace firmado valido', function () {
    $user = User::factory()->create([
        'email' => 'ana@example.org',
        'email_subscribed' => true,
    ]);

    $response = $this->get(EnlaceBajaEmail::generar($user));

    $response->assertRedirect('/');
    $response->assertSessionHas('success');

    expect($user->fresh()?->email_subscribed)->toBeFalse();
});

it('procesa el one-click unsubscribe con una firma valida', function () {
    $user = User::factory()->create([
        'email' => 'ana@example.org',
        'email_subscribed' => true,
    ]);

    $response = $this->post(EnlaceBajaEmail::generar($user));

    $response->assertOk();
    expect($user->fresh()?->email_subscribed)->toBeFalse();
});

it('rechaza la baja por get cuando la firma es invalida', function () {
    $user = User::factory()->create([
        'email' => 'ana@example.org',
        'email_subscribed' => true,
    ]);

    $response = $this->get('/email/baja?id='.$user->id.'&signature=invalida');

    $response->assertRedirect('/');
    $response->assertSessionHas('error');

    expect($user->fresh()?->email_subscribed)->toBeTrue();
});

it('responde neutro por post cuando la firma es invalida', function () {
    $user = User::factory()->create([
        'email' => 'ana@example.org',
        'email_subscribed' => true,
    ]);

    $response = $this->post('/email/baja?id='.$user->id.'&signature=invalida');

    $response->assertOk();
    expect($user->fresh()?->email_subscribed)->toBeTrue();
});
