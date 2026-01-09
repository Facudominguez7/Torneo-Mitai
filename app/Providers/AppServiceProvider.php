<?php

namespace App\Providers;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\Event as FacadesEvent;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     * @return void
     */
    public function boot(): void
    {
        // Registrar listener para logear emails enviados
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Notifications\Events\NotificationSent::class,
            \App\Listeners\LogNotificacionEnviada::class
        );

        // Personalizar URL de verificación para usar query parameters (evita 403 Forbidden)
        \Illuminate\Auth\Notifications\VerifyEmail::createUrlUsing(function (object $notifiable) {
            return \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'verification.verify',
                \Illuminate\Support\Carbon::now()->addMinutes(\Illuminate\Support\Facades\Config::get('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );
        });

        if($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
