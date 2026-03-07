<?php

use App\Models\User;
use App\Services\EmailVerificationCodeService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get('/verify-email');

    $response->assertStatus(200);
});

test('email can be verified', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('home', absolute: false).'?verified=1');
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')]
    );

    $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('email can be verified with a six digit code', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'codigo@gmail.com',
        'requires_email_verification_for_notifications' => true,
    ]);

    Event::fake();

    $code = app(EmailVerificationCodeService::class)->issue($user);

    $response = $this->actingAs($user)->post('/verify-email/code', [
        'code' => $code,
    ]);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('home', absolute: false).'?verified=1');
});

test('new users must verify email before receiving tournament emails', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'nuevo@gmail.com',
        'email_subscribed' => true,
        'requires_email_verification_for_notifications' => true,
    ]);

    expect($user->quiereRecibirEmails())->toBeFalse();

    $user->forceFill(['email_verified_at' => now()])->save();

    expect($user->fresh()->quiereRecibirEmails())->toBeTrue();
});

test('legacy users keep email notifications even if they are not verified', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'legacy@gmail.com',
        'email_subscribed' => true,
        'requires_email_verification_for_notifications' => false,
    ]);

    expect($user->quiereRecibirEmails())->toBeTrue();
});
