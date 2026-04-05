<?php

namespace App\Listeners;

use App\Models\SupresionEmail;

class RegistrarSupresionCorreoResend
{
    private const EVENTO_EMAIL_BOUNCED = 'Resend\\Laravel\\Events\\EmailBounced';

    private const EVENTO_EMAIL_COMPLAINED = 'Resend\\Laravel\\Events\\EmailComplained';

    public function handle(object $event): void
    {
        $motivo = match ($event::class) {
            self::EVENTO_EMAIL_BOUNCED => SupresionEmail::MOTIVO_REBOTE,
            self::EVENTO_EMAIL_COMPLAINED => SupresionEmail::MOTIVO_QUEJA,
            default => null,
        };

        if ($motivo === null) {
            return;
        }

        $emails = collect(data_get($event, 'payload.data.to', []))
            ->filter(fn ($email) => is_string($email) && trim($email) !== '')
            ->map(fn (string $email) => mb_strtolower(trim($email)))
            ->unique()
            ->values();

        if ($emails->isEmpty()) {
            return;
        }

        $providerMessageId = data_get($event, 'payload.data.email_id');
        $payload = data_get($event, 'payload');

        foreach ($emails as $email) {
            SupresionEmail::suprimir(
                $email,
                $motivo,
                is_string($providerMessageId) ? $providerMessageId : null,
                is_array($payload) ? $payload : null,
            );
        }
    }
}
