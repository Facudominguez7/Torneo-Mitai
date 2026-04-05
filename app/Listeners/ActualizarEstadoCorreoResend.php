<?php

namespace App\Listeners;

use App\Models\NotificationDispatch;

class ActualizarEstadoCorreoResend
{
    private const EVENTO_EMAIL_SENT = 'Resend\\Laravel\\Events\\EmailSent';

    private const EVENTO_EMAIL_DELIVERED = 'Resend\\Laravel\\Events\\EmailDelivered';

    private const EVENTO_EMAIL_BOUNCED = 'Resend\\Laravel\\Events\\EmailBounced';

    private const EVENTO_EMAIL_COMPLAINED = 'Resend\\Laravel\\Events\\EmailComplained';

    private const EVENTO_EMAIL_FAILED = 'Resend\\Laravel\\Events\\EmailFailed';

    public function handle(object $event): void
    {
        $providerMessageId = data_get($event, 'payload.data.email_id');

        if (! is_string($providerMessageId) || $providerMessageId === '') {
            return;
        }

        $dispatch = NotificationDispatch::query()
            ->where('provider_message_id', $providerMessageId)
            ->latest('id')
            ->first();

        if ($dispatch === null) {
            return;
        }

        $status = $this->status($event);

        if ($status === null) {
            return;
        }

        $dispatch->updateStatus($status, $this->errorMessage(data_get($event, 'payload', [])));
    }

    private function status(object $event): ?string
    {
        return match ($event::class) {
            self::EVENTO_EMAIL_SENT => NotificationDispatch::STATUS_SENT,
            self::EVENTO_EMAIL_DELIVERED => NotificationDispatch::STATUS_DELIVERED,
            self::EVENTO_EMAIL_BOUNCED => NotificationDispatch::STATUS_BOUNCED,
            self::EVENTO_EMAIL_COMPLAINED => NotificationDispatch::STATUS_COMPLAINED,
            self::EVENTO_EMAIL_FAILED => NotificationDispatch::STATUS_FAILED,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function errorMessage(array $payload): ?string
    {
        $message = data_get($payload, 'data.bounce.message')
            ?? data_get($payload, 'data.error.message')
            ?? data_get($payload, 'data.reason');

        return is_string($message) && trim($message) !== '' ? trim($message) : null;
    }
}
