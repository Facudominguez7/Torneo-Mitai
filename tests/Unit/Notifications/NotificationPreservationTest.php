<?php

use App\Models\User;
use App\Notifications\ResultadoCargado;
use App\Notifications\VerifyEmailCodeNotification;
use App\Services\EnlaceBajaEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function preservationResultadoPayload(): object
{
    return (object) [
        'id' => 22,
        'idEdicion' => 9,
        'nombre_local' => 'Mitai FC',
        'nombre_visitante' => 'Estrella Norte',
        'golesEquipoLocal' => 4,
        'golesEquipoVisitante' => 1,
        'nombre_categoria' => 'Sub 17',
    ];
}

dataset('email eligibility cases', [
    'suscrito y verificado' => [
        [
            'email' => 'ana@example.org',
            'email_subscribed' => true,
            'requires_email_verification_for_notifications' => false,
            'email_verified_at' => now(),
        ],
        true,
    ],
    'desuscripto' => [
        [
            'email' => 'ana@example.org',
            'email_subscribed' => false,
            'requires_email_verification_for_notifications' => false,
            'email_verified_at' => now(),
        ],
        false,
    ],
    'requiere verificacion y no verifico' => [
        [
            'email' => 'ana@example.org',
            'email_subscribed' => true,
            'requires_email_verification_for_notifications' => true,
            'email_verified_at' => null,
        ],
        false,
    ],
    'dominio bloqueado' => [
        [
            'email' => 'ana@mail.ru',
            'email_subscribed' => true,
            'requires_email_verification_for_notifications' => false,
            'email_verified_at' => now(),
        ],
        false,
    ],
]);

dataset('resultado channel cases', [
    'solo mail' => [true, false, false, true, false, 'ana@example.org'],
    'solo push si no quiere mail' => [false, false, true, false, true, 'ana@example.org'],
    'mail y push' => [true, false, true, true, true, 'ana@example.org'],
    'push aun si falta verificar email' => [true, true, true, false, true, 'ana@example.org'],
    'sin mail por dominio bloqueado' => [true, false, false, false, false, 'ana@mail.ru'],
]);

it('preserva las reglas actuales para decidir si un usuario recibe emails', function (array $attributes, bool $expected) {
    $user = User::factory()->create(array_merge([
        'name' => 'Ana',
        'password' => bcrypt('password'),
    ], $attributes));

    expect($user->quiereRecibirEmails())->toBe($expected);
})->with('email eligibility cases');

it('preserva el gating de mail y push en resultado cargado', function (
    bool $emailSubscribed,
    bool $requiresVerification,
    bool $withPushSubscription,
    bool $expectsMail,
    bool $expectsPush,
    string $email,
) {
    $user = User::factory()->create([
        'name' => 'Ana',
        'email' => $email,
        'email_subscribed' => $emailSubscribed,
        'requires_email_verification_for_notifications' => $requiresVerification,
        'email_verified_at' => $requiresVerification ? null : now(),
    ]);

    if ($withPushSubscription) {
        DB::table(config('webpush.table_name', 'push_subscriptions'))->insert([
            'subscribable_type' => User::class,
            'subscribable_id' => $user->id,
            'endpoint' => 'https://push.example.test/'.$user->id,
            'public_key' => 'public-key',
            'auth_token' => 'auth-token',
            'content_encoding' => 'aesgcm',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $channels = (new ResultadoCargado(preservationResultadoPayload()))->via($user);

    expect(in_array('mail', $channels, true))->toBe($expectsMail);
    expect(collect($channels)->contains(WebPushChannel::class))->toBe($expectsPush);
})->with('resultado channel cases');

it('preserva el contenido del email de resultado cargado', function () {
    $user = User::factory()->create([
        'name' => 'Ana',
        'email' => 'ana@example.org',
        'email_subscribed' => true,
        'requires_email_verification_for_notifications' => false,
        'email_verified_at' => now(),
    ]);

    $message = (new ResultadoCargado(preservationResultadoPayload()))->toMail($user);

    expect($message->subject)->toBe("Resultado: Mitai FC vs Estrella Norte - Torneos Mita'i");
    expect($message->introLines)->toContain('Se ha cargado un nuevo resultado:');
    expect($message->introLines)->toContain('**Mitai FC** 4 - 1 **Estrella Norte**');
    expect(collect($message->introLines)->contains(fn (string $line) => str_contains($line, 'Sub 17')))->toBeTrue();
    expect($message->actionText)->toBe('Ver fixture completo');
    expect($message->actionUrl)->toBe(url('/fixture?idEdicion=9'));
    $outro = implode(' ', $message->outroLines);

    expect($outro)->toContain(EnlaceBajaEmail::generar($user));
    expect($outro)->not->toContain('token=');
});

it('preserva el flujo actual del email de verificacion por codigo', function () {
    $user = User::factory()->create([
        'name' => 'Ana',
        'email' => 'ana@example.org',
    ]);

    $message = (new VerifyEmailCodeNotification('123456'))->toMail($user);

    expect($message->subject)->toBe("Tu codigo de verificacion - Torneos Mita'i");
    expect($message->introLines)->toContain('Usa este codigo de 6 digitos para verificar tu email y activar las notificaciones por correo:');
    expect($message->introLines)->toContain('Codigo de verificacion: 123456');
    expect($message->actionText)->toBe('Ingresar codigo');
    expect($message->actionUrl)->toBe(route('verification.notice'));
});
