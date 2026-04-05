<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class NotificationDispatch extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_BOUNCED = 'bounced';

    public const STATUS_FAILED = 'failed';

    public const STATUS_COMPLAINED = 'complained';

    public const STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_SENT,
        self::STATUS_DELIVERED,
        self::STATUS_BOUNCED,
        self::STATUS_FAILED,
        self::STATUS_COMPLAINED,
    ];

    protected $fillable = [
        'user_id',
        'notification_type',
        'channel',
        'provider',
        'provider_message_id',
        'recipient_email',
        'status',
        'status_updated_at',
        'payload',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status_updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function updateStatus(string $status, ?string $errorMessage = null): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Estado de notificacion no soportado: '.$status);
        }

        $this->forceFill([
            'status' => $status,
            'status_updated_at' => now(),
            'error_message' => $errorMessage,
        ])->save();
    }
}
