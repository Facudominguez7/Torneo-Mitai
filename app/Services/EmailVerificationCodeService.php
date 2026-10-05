<?php

namespace App\Services;

use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class EmailVerificationCodeService
{
    public const CODE_LENGTH = 6;

    public const EXPIRES_IN_MINUTES = 15;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_INVALID = 'invalid';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_MISSING = 'missing';

    public const STATUS_LOCKED = 'locked';

    public function issue(User $user): string
    {
        $code = str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);

        EmailVerificationCode::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(self::EXPIRES_IN_MINUTES),
                'last_sent_at' => now(),
            ]
        );

        return $code;
    }

    public function secondsUntilResend(User $user): int
    {
        $verificationCode = EmailVerificationCode::query()
            ->where('user_id', $user->id)
            ->first();

        if (! $verificationCode?->last_sent_at) {
            return 0;
        }

        $availableAt = $verificationCode->last_sent_at->copy()->addSeconds(self::RESEND_COOLDOWN_SECONDS);

        if ($availableAt->isPast()) {
            return 0;
        }

        return now()->diffInSeconds($availableAt);
    }

    public function verify(User $user, string $code): string
    {
        $verificationCode = EmailVerificationCode::query()
            ->where('user_id', $user->id)
            ->first();

        if (! $verificationCode) {
            return self::STATUS_MISSING;
        }

        if ($verificationCode->expires_at->isPast()) {
            $verificationCode->delete();

            return self::STATUS_EXPIRED;
        }

        if ($verificationCode->attempts >= self::MAX_ATTEMPTS) {
            $verificationCode->delete();

            return self::STATUS_LOCKED;
        }

        $attempts = $verificationCode->attempts + 1;

        $verificationCode->forceFill([
            'attempts' => $attempts,
        ])->save();

        if (! Hash::check($code, $verificationCode->code_hash)) {
            if ($attempts >= self::MAX_ATTEMPTS) {
                $verificationCode->delete();

                return self::STATUS_LOCKED;
            }

            return self::STATUS_INVALID;
        }

        $verificationCode->delete();

        return self::STATUS_VERIFIED;
    }

    public function clear(User $user): void
    {
        EmailVerificationCode::query()
            ->where('user_id', $user->id)
            ->delete();
    }
}
