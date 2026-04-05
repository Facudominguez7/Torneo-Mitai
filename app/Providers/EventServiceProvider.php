<?php

namespace App\Providers;

use App\Listeners\ActualizarEstadoCorreoResend;
use App\Listeners\CacheNotificationDispatchContext;
use App\Listeners\LogNotificacionEnviada;
use App\Listeners\PersistNotificationDispatch;
use App\Listeners\RegistrarSupresionCorreoResend;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        NotificationSending::class => [
            CacheNotificationDispatchContext::class,
        ],
        NotificationSent::class => [
            PersistNotificationDispatch::class,
            LogNotificacionEnviada::class,
        ],
        'Resend\\Laravel\\Events\\EmailSent' => [
            ActualizarEstadoCorreoResend::class,
        ],
        'Resend\\Laravel\\Events\\EmailDelivered' => [
            ActualizarEstadoCorreoResend::class,
        ],
        'Resend\\Laravel\\Events\\EmailFailed' => [
            ActualizarEstadoCorreoResend::class,
        ],
        'Resend\\Laravel\\Events\\EmailBounced' => [
            ActualizarEstadoCorreoResend::class,
            RegistrarSupresionCorreoResend::class,
        ],
        'Resend\\Laravel\\Events\\EmailComplained' => [
            ActualizarEstadoCorreoResend::class,
            RegistrarSupresionCorreoResend::class,
        ],
    ];
}
