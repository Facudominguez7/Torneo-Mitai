<?php

namespace App\Services;

use Symfony\Component\Mime\Email;

class NotificationDispatchMetadata
{
    public const HEADER_NAME = 'X-TM-Notification-ID';

    public const HEADER_IDEMPOTENCY_KEY = 'Resend-Idempotency-Key';

    public static function attach(Email $message, ?string $notificationId): void
    {
        if (! is_string($notificationId) || $notificationId === '') {
            return;
        }

        $headers = $message->getHeaders();

        if ($headers->has(self::HEADER_NAME)) {
            $headers->remove(self::HEADER_NAME);
        }

        if ($headers->has(self::HEADER_IDEMPOTENCY_KEY)) {
            $headers->remove(self::HEADER_IDEMPOTENCY_KEY);
        }

        $headers->addTextHeader(self::HEADER_NAME, $notificationId);
        $headers->addTextHeader(self::HEADER_IDEMPOTENCY_KEY, self::idempotencyKey($notificationId));
    }

    public static function idempotencyKey(string $notificationId): string
    {
        return 'tm-notificacion/'.$notificationId;
    }

    public static function extract(Email $message): ?string
    {
        $header = $message->getHeaders()->get(self::HEADER_NAME);

        if ($header === null) {
            return null;
        }

        $value = trim($header->getBodyAsString());

        return $value !== '' ? $value : null;
    }
}
