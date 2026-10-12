<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create([
        'email' => 'actual@gmail.com',
    ]);

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'actualizado@gmail.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('actualizado@gmail.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create([
        'email' => 'estable@gmail.com',
    ]);

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertNull($user->fresh());
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrorsIn('userDeletion', 'password')
        ->assertRedirect('/profile');

    $this->assertNotNull($user->fresh());
});

test('rejected example.com email leaves the profile unchanged and reports a validation error', function () {
    $user = User::factory()->create([
        'name' => 'Nombre Original',
        'email' => 'original@gmail.com',
        'email_verified_at' => now(),
    ]);

    $fechaVerificacion = $user->fresh()->email_verified_at->toDateTimeString();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Nombre Modificado',
            'email' => 'rechazado@example.com',
        ]);

    $response->assertSessionHasErrors('email');

    $user->refresh();

    $this->assertSame('Nombre Original', $user->name);
    $this->assertSame('original@gmail.com', $user->email);
    $this->assertNotNull($user->email_verified_at);
    $this->assertSame($fechaVerificacion, $user->email_verified_at?->toDateTimeString());
});
