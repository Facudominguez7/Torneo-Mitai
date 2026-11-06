<?php

// Rebotes y quejas que informa Resend: quedan fechados en el suscriptor y lo dan de baja.

use App\Models\Suscriptor;
use App\Services\SuscripcionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

it('guarda la fecha del rebote y de la queja en columnas propias del suscriptor', function (): void {
    expect(Schema::hasColumn('suscriptores', 'rebote_at'))->toBeTrue()
        ->and(Schema::hasColumn('suscriptores', 'queja_at'))->toBeTrue();

    $suscriptor = Suscriptor::factory()->pendiente()->create(['email' => 'rebote@correo.com']);
    expect($suscriptor->rebote_at)->toBeNull()
        ->and($suscriptor->queja_at)->toBeNull();

    $suscriptor->forceFill(['rebote_at' => now()->subDay(), 'queja_at' => now()->subHour()])->save();

    $suscriptor->refresh();
    expect($suscriptor->rebote_at)->toBeInstanceOf(Carbon::class)
        ->and($suscriptor->queja_at)->toBeInstanceOf(Carbon::class)
        ->and($suscriptor->rebote_at->toDateTimeString())->toBe(now()->subDay()->toDateTimeString())
        ->and($suscriptor->queja_at->toDateTimeString())->toBe(now()->subHour()->toDateTimeString());
});

it('da de baja con la fecha del evento a cada suscriptor de los destinatarios y respeta la baja anterior', function (): void {
    $nuncaDeBaja = Suscriptor::factory()->activo()->create(['email' => 'madre@correo.com']);
    $yaDeBaja = Suscriptor::factory()->activo()->create(['email' => 'baja@correo.com', 'baja_at' => now()->subDays(2)]);
    $emitido = now()->subHour()->startOfSecond();

    app(SuscripcionService::class)->registrarReboteDesdeResend('email.bounced', [
        'to' => [' Madre@Correo.com ', 'BAJA@correo.com', 'otra@correo.com'],
        'bounce' => ['type' => 'Permanent'],
    ], $emitido);

    $nuncaDeBaja->refresh();
    $yaDeBaja->refresh();
    expect($nuncaDeBaja->rebote_at->toDateTimeString())->toBe($emitido->toDateTimeString())
        ->and($nuncaDeBaja->baja_at->toDateTimeString())->toBe($emitido->toDateTimeString())
        ->and($nuncaDeBaja->estaActivo())->toBeFalse()
        ->and($yaDeBaja->rebote_at->toDateTimeString())->toBe($emitido->toDateTimeString())
        ->and($yaDeBaja->baja_at->toDateTimeString())->toBe(now()->subDays(2)->toDateTimeString())
        ->and(Suscriptor::query()->count())->toBe(2);
});

it('no toca a nadie cuando los destinatarios no son suscriptores', function (): void {
    $suscriptor = Suscriptor::factory()->activo()->create(['email' => 'madre@correo.com']);

    app(SuscripcionService::class)->registrarReboteDesdeResend('email.complained', [
        'to' => ['otra@correo.com'],
    ], now());

    $suscriptor->refresh();
    expect($suscriptor->queja_at)->toBeNull()
        ->and($suscriptor->baja_at)->toBeNull()
        ->and($suscriptor->estaActivo())->toBeTrue();
});
