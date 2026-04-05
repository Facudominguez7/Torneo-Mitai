<?php

namespace App\Services;

class NotificationDispatchContextStore
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $contexts = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public function put(string $notificationId, array $context): void
    {
        $this->contexts[$notificationId] = $context;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pull(string $notificationId): ?array
    {
        $context = $this->contexts[$notificationId] ?? null;

        unset($this->contexts[$notificationId]);

        return $context;
    }
}
