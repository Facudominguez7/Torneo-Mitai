<?php

use App\Listeners\CacheNotificationDispatchContext;
use App\Listeners\PersistNotificationDispatch;
use App\Models\NotificationDispatch;
use App\Models\User;
use App\Notifications\ResultadoCargado;
use App\Services\NotificationDispatchContextStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

uses(RefreshDatabase::class);

function integrationResultadoPayload(): object
{
    return (object) [
        'id' => 44,
        'idEdicion' => 12,
        'nombre_local' => 'Mitai FC',
        'nombre_visitante' => 'Estrella Norte',
        'golesEquipoLocal' => 2,
        'golesEquipoVisitante' => 0,
        'nombre_categoria' => 'Sub 13',
    ];
}

it('persiste el provider message id desde X-Resend-Email-ID', function () {
    $user = User::factory()->create([
        'name' => 'Ana',
        'email' => 'ana@example.org',
        'email_subscribed' => true,
        'requires_email_verification_for_notifications' => false,
        'email_verified_at' => now(),
    ]);

    $notification = new ResultadoCargado(integrationResultadoPayload());
    $notification->id = (string) Str::uuid();

    $store = app(NotificationDispatchContextStore::class);

    (new CacheNotificationDispatchContext($store))->handle(new NotificationSending($user, $notification, 'mail'));

    $email = (new Email)
        ->from('no-reply@notificaciones.torneosmitai.com')
        ->to('ana@example.org')
        ->subject('Resultado de prueba')
        ->text('Contenido de prueba');

    $email->getHeaders()->addTextHeader('X-Resend-Email-ID', 'email_db_1');

    $sentMessage = new SentMessage(
        $email,
        new Envelope(
            new Address('no-reply@notificaciones.torneosmitai.com'),
            [new Address('ana@example.org')]
        )
    );

    (new PersistNotificationDispatch($store))->handle(new NotificationSent($user, $notification, 'mail', $sentMessage));

    $dispatch = NotificationDispatch::query()->first();

    expect($dispatch)->not()->toBeNull();
    expect($dispatch?->provider_message_id)->toBe('email_db_1');
    expect($dispatch?->status)->toBe(NotificationDispatch::STATUS_SENT);
    expect($dispatch?->recipient_email)->toBe('ana@example.org');
});

it('no crea un despacho si el transporte no expone el id real de resend', function () {
    $user = User::factory()->create([
        'name' => 'Ana',
        'email' => 'ana@example.org',
        'email_subscribed' => true,
        'requires_email_verification_for_notifications' => false,
        'email_verified_at' => now(),
    ]);

    $notification = new ResultadoCargado(integrationResultadoPayload());
    $notification->id = (string) Str::uuid();

    $store = app(NotificationDispatchContextStore::class);

    (new CacheNotificationDispatchContext($store))->handle(new NotificationSending($user, $notification, 'mail'));

    $email = (new Email)
        ->from('no-reply@notificaciones.torneosmitai.com')
        ->to('ana@example.org')
        ->subject('Resultado de prueba')
        ->text('Contenido de prueba');

    $sentMessage = new SentMessage(
        $email,
        new Envelope(
            new Address('no-reply@notificaciones.torneosmitai.com'),
            [new Address('ana@example.org')]
        )
    );

    (new PersistNotificationDispatch($store))->handle(new NotificationSent($user, $notification, 'mail', $sentMessage));

    expect(DB::table('notification_dispatches')->count())->toBe(0);
});
