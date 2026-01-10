<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
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

    /**
     * Verificar si el usuario desea recibir notificaciones por email.
     * 
     * Un usuario recibe emails si:
     * - Tiene el campo email_subscribed en true
     * - Tiene su email verificado
     *
     * @return bool
     */
    public function quiereRecibirEmails(): bool
    {
        return $this->email_subscribed && $this->hasVerifiedEmail();
    }

    /**
     * Scope para filtrar usuarios suscritos a notificaciones por email.
     * 
     * Uso: User::suscritosAEmail()->get()
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeSuscritosAEmail(Builder $query): Builder
    {
        return $query->where('email_subscribed', true)
                     ->whereNotNull('email_verified_at');
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
        ];
    }
}
