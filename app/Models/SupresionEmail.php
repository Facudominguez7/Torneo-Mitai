<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupresionEmail extends Model
{
    use HasFactory;

    public const MOTIVO_REBOTE = 'rebote';

    public const MOTIVO_QUEJA = 'queja';

    protected $table = 'email_suppressions';

    protected $fillable = [
        'email',
        'motivo',
        'provider',
        'provider_message_id',
        'payload',
        'suprimido_at',
        'rehabilitado_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'suprimido_at' => 'datetime',
            'rehabilitado_at' => 'datetime',
        ];
    }

    public static function suprimir(string $email, string $motivo, ?string $providerMessageId = null, ?array $payload = null): void
    {
        $emailNormalizado = mb_strtolower(trim($email));

        if ($emailNormalizado === '') {
            return;
        }

        self::query()->updateOrCreate(
            ['email' => $emailNormalizado],
            [
                'motivo' => $motivo,
                'provider' => 'resend',
                'provider_message_id' => $providerMessageId,
                'payload' => $payload,
                'suprimido_at' => now(),
                'rehabilitado_at' => null,
            ]
        );
    }

    public static function estaActivaPara(?string $email): bool
    {
        $emailNormalizado = mb_strtolower(trim((string) $email));

        if ($emailNormalizado === '') {
            return false;
        }

        return self::query()
            ->where('email', $emailNormalizado)
            ->whereNull('rehabilitado_at')
            ->exists();
    }

    public static function aplicarExclusionActivas(Builder $query, string $columnaEmail = 'users.email'): Builder
    {
        return $query->whereNotExists(function ($subQuery) use ($columnaEmail) {
            $subQuery->selectRaw('1')
                ->from('email_suppressions')
                ->whereColumn('email_suppressions.email', $columnaEmail)
                ->whereNull('email_suppressions.rehabilitado_at');
        });
    }
}
