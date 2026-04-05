<?php

use App\Models\NotificationDispatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function resendWebhookHeaders(string $payload, string $messageId = 'msg_test', ?int $timestamp = null): array
{
    $timestamp ??= now()->timestamp;
    $secret = 'tm-webhook-secret';

    config()->set('resend.webhook.secret', 'whsec_'.base64_encode($secret));
    config()->set('resend.webhook.tolerance', 300);

    $signature = base64_encode(hash_hmac('sha256', $messageId.'.'.$timestamp.'.'.$payload, $secret, true));

    return [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_SVIX_ID' => $messageId,
        'HTTP_SVIX_TIMESTAMP' => (string) $timestamp,
        'HTTP_SVIX_SIGNATURE' => 'v1,'.$signature,
    ];
}

it('actualiza el estado de un despacho cuando recibe un webhook valido de resend', function () {
    $user = User::factory()->create([
        'email' => 'ana@example.org',
        'email_subscribed' => true,
    ]);

    $dispatch = NotificationDispatch::create([
        'user_id' => $user->id,
        'notification_type' => 'App\\Notifications\\ResultadoCargado',
        'channel' => 'mail',
        'provider' => 'resend',
        'provider_message_id' => 'email_123',
        'recipient_email' => 'ana@example.org',
        'status' => NotificationDispatch::STATUS_SENT,
        'status_updated_at' => now(),
    ]);

    $payload = json_encode([
        'type' => 'email.delivered',
        'data' => [
            'email_id' => 'email_123',
        ],
    ], JSON_THROW_ON_ERROR);

    $response = $this->call('POST', '/webhooks/resend', [], [], [], resendWebhookHeaders($payload), $payload);

    $response->assertOk();

    expect($dispatch->fresh()?->status)->toBe(NotificationDispatch::STATUS_DELIVERED);
});

it('registra una supresion cuando resend informa un bounce', function () {
    $user = User::factory()->create([
        'email' => 'ana@example.org',
        'email_subscribed' => true,
    ]);

    NotificationDispatch::create([
        'user_id' => $user->id,
        'notification_type' => 'App\\Notifications\\ResultadoCargado',
        'channel' => 'mail',
        'provider' => 'resend',
        'provider_message_id' => 'email_bounced_1',
        'recipient_email' => 'ana@example.org',
        'status' => NotificationDispatch::STATUS_SENT,
        'status_updated_at' => now(),
    ]);

    $payload = json_encode([
        'type' => 'email.bounced',
        'data' => [
            'email_id' => 'email_bounced_1',
            'to' => ['ana@example.org'],
            'bounce' => [
                'message' => 'Mailbox unavailable',
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $response = $this->call('POST', '/webhooks/resend', [], [], [], resendWebhookHeaders($payload), $payload);

    $response->assertOk();

    expect(DB::table('email_suppressions')->count())->toBe(1);
    expect(DB::table('email_suppressions')->value('email'))->toBe('ana@example.org');
    expect(DB::table('email_suppressions')->value('motivo'))->toBe('rebote');
});

it('rechaza webhooks con firma invalida', function () {
    config()->set('resend.webhook.secret', 'whsec_'.base64_encode('tm-webhook-secret'));

    $payload = json_encode([
        'type' => 'email.delivered',
        'data' => [
            'email_id' => 'email_123',
        ],
    ], JSON_THROW_ON_ERROR);

    $response = $this->call('POST', '/webhooks/resend', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_SVIX_ID' => 'msg_test',
        'HTTP_SVIX_TIMESTAMP' => (string) now()->timestamp,
        'HTTP_SVIX_SIGNATURE' => 'v1,firma-invalida',
    ], $payload);

    $response->assertForbidden();
});
