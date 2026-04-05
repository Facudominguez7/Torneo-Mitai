<?php

namespace App\Listeners;

use App\Services\NotificationDispatchContextStore;
use Illuminate\Notifications\Events\NotificationSending;

class CacheNotificationDispatchContext
{
    public function __construct(private readonly NotificationDispatchContextStore $contextStore) {}

    public function handle(NotificationSending $event): void
    {
        if ($event->channel !== 'mail') {
            return;
        }

        $notificationId = $event->notification->id;

        if (! is_string($notificationId) || $notificationId === '') {
            return;
        }

        $payload = $this->notificationPayload($event->notification, $event->notifiable);

        $payload['notification_id'] = $notificationId;

        $this->contextStore->put($notificationId, [
            'user_id' => $event->notifiable->id ?? null,
            'notification_type' => get_class($event->notification),
            'recipient_email' => $this->recipientEmail($event->notifiable, $event->notification),
            'payload' => $payload,
        ]);
    }

    private function recipientEmail(mixed $notifiable, mixed $notification): ?string
    {
        $route = $notifiable->routeNotificationFor('mail', $notification);

        if (is_string($route) && $route !== '') {
            return $route;
        }

        if (is_array($route) && $route !== []) {
            $key = array_key_first($route);

            if (is_string($key) && $key !== '') {
                return $key;
            }

            $value = reset($route);

            return is_string($value) && $value !== '' ? $value : null;
        }

        return $notifiable->email ?? null;
    }

    private function notificationPayload(object $notification, mixed $notifiable): array
    {
        if (! method_exists($notification, 'toArray')) {
            return [];
        }

        $payload = $notification->toArray($notifiable);

        return is_array($payload) ? $payload : [];
    }
}
