<?php

namespace App\Listeners;

use App\Models\NotificationDispatch;
use App\Services\NotificationDispatchContextStore;
use Illuminate\Notifications\Events\NotificationSent;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Message as MimeMessage;

class PersistNotificationDispatch
{
    public function __construct(private readonly NotificationDispatchContextStore $contextStore) {}

    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'mail') {
            return;
        }

        $notificationId = $event->notification->id;

        if (! is_string($notificationId) || $notificationId === '') {
            return;
        }

        $providerMessageId = $this->providerMessageId($event->response);

        if ($providerMessageId === null) {
            return;
        }

        $context = $this->contextStore->pull($notificationId) ?? [
            'user_id' => $event->notifiable->id ?? null,
            'notification_type' => get_class($event->notification),
            'recipient_email' => $event->notifiable->email ?? null,
            'payload' => $this->notificationPayload($event->notification, $event->notifiable),
        ];

        NotificationDispatch::updateOrCreate(
            ['provider_message_id' => $providerMessageId],
            [
                'user_id' => $context['user_id'] ?? null,
                'notification_type' => $context['notification_type'] ?? get_class($event->notification),
                'channel' => 'mail',
                'provider' => 'resend',
                'recipient_email' => $context['recipient_email'] ?? ($event->notifiable->email ?? null),
                'status' => NotificationDispatch::STATUS_SENT,
                'status_updated_at' => now(),
                'payload' => $context['payload'] ?? null,
                'error_message' => null,
            ]
        );
    }

    private function providerMessageId(mixed $response): ?string
    {
        if (is_object($response) && method_exists($response, 'getSymfonySentMessage')) {
            $response = $response->getSymfonySentMessage();
        }

        if (! $response instanceof SymfonySentMessage) {
            return null;
        }

        return $this->providerMessageIdFromHeader($response);
    }

    private function providerMessageIdFromHeader(SymfonySentMessage $message): ?string
    {
        foreach ([$message->getOriginalMessage(), $message->getMessage()] as $rawMessage) {
            if (! $rawMessage instanceof MimeMessage) {
                continue;
            }

            $header = $rawMessage->getHeaders()->get('X-Resend-Email-ID');

            if ($header === null) {
                continue;
            }

            $messageId = trim($header->getBodyAsString());

            if ($messageId !== '') {
                return $messageId;
            }
        }

        return null;
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
