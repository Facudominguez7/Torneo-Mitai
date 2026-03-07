<?php

namespace App\Models;

use App\Notifications\VerifyEmailCodeNotification;
use App\Services\EmailDomainGuard;
use App\Services\EmailVerificationCodeService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Illuminate\Database\Eloquent\Builder;

/**
 * Modelo de Usuario.
 * 
 * Representa a los usuarios del sistema con soporte para:
 * - Autenticación estándar y OAuth (Google)
 * - Notificaciones push (WebPush)
 * - Notificaciones por email con opción de desuscripción
 * 
 * @property int $id
 * @property string $name
 * @property string $email
 * @property bool $email_subscribed Indica si el usuario recibe emails de notificación
 * @property string|null $email_verified_at
 * @property string $rol admin|editor|regular
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasPushSubscriptions;

    /**
     * Atributos que se pueden asignar masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_subscribed',
        'requires_email_verification_for_notifications',
        'restablecer_password',
        'last_notification_at',
    ];

    /**
     * Atributos ocultos en serialización.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Verificar si el usuario es administrador.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->rol == 'admin';
    }

    public function emailVerificationCode(): HasOne
    {
        return $this->hasOne(EmailVerificationCode::class);
    }

    public function sendEmailVerificationNotification(): void
    {
        $code = app(EmailVerificationCodeService::class)->issue($this);

        $this->notify(new VerifyEmailCodeNotification($code));
    }

    /**
     * Verificar si el usuario desea recibir notificaciones por email.
     * 
     * Regla de negocio actual:
     * - Los usuarios legacy conservan el comportamiento historico.
     * - Las cuentas nuevas marcadas con politica reforzada deben verificar
     *   el correo antes de recibir emails del torneo.
     *
     * El usuario conserva siempre la opción de baja desde el enlace incluido
     * en cada correo notificado.
     *
     * @return bool
     */
    public function quiereRecibirEmails(): bool
    {
        return (bool) $this->email_subscribed
            && ! empty($this->email)
            && ! EmailDomainGuard::isBlocked($this->email)
            && (! $this->requires_email_verification_for_notifications || $this->hasVerifiedEmail());
    }

    /**
     * Scope para filtrar usuarios suscritos a notificaciones por email.
     * 
     * Mantiene compatibilidad con usuarios legacy, pero exige email verificado
     * para las cuentas nuevas que usan la politica reforzada.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeSuscritosAEmail(Builder $query): Builder
    {
        $query->where('email_subscribed', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->where(function (Builder $emailPolicyQuery) {
                $emailPolicyQuery->where('requires_email_verification_for_notifications', false)
                    ->orWhereNotNull('email_verified_at');
            });

        return EmailDomainGuard::applyBlockedDomainsFilter($query);
    }

    /**
     * Obtener los atributos que deben ser casteados.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_notification_at' => 'datetime',
            'password' => 'hashed',
            'email_subscribed' => 'boolean',
            'requires_email_verification_for_notifications' => 'boolean',
        ];
    }
}
