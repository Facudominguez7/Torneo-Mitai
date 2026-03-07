<?php

namespace App\Providers;

use App\Models\Edicion;
use App\Services\EmailDeliverabilityHeaders;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event as FacadesEvent;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewContract;

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

        \Illuminate\Auth\Notifications\VerifyEmail::toMailUsing(function (object $notifiable, string $verificationUrl) {
            return (new MailMessage)
                ->from(EmailDeliverabilityHeaders::fromAddress(), EmailDeliverabilityHeaders::fromName())
                ->replyTo(EmailDeliverabilityHeaders::replyToAddress(), EmailDeliverabilityHeaders::replyToName())
                ->subject('Verificá tu email - Torneos Mita\'i')
                ->greeting('Hola '.$notifiable->name.',')
                ->line('Gracias por registrarte en Torneos Mita\'i.')
                ->line('Para activar tu cuenta y recibir avisos del torneo, verificá tu dirección de correo.')
                ->action('Verificar email', $verificationUrl)
                ->line('Si no creaste esta cuenta, podés ignorar este mensaje.')
                ->salutation('Saludos, Torneos Mita\'i')
                ->withSymfonyMessage(function ($message) {
                    EmailDeliverabilityHeaders::applyTransactional($message, 'verificacion_email');
                });
        });

        if($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        /**
         * Compartir contexto global del panel en el layout base.
         *
         * Evita que cada controlador tenga que enviar siempre `ediciones`
         * y `EdicionSeleccionada` manualmente.
         */
        View::composer('Panel.admin', function (ViewContract $view): void {
            $ediciones = Edicion::orderByDesc('id')->get();

            $idEdicionActual = request()->attributes->get('panel.idEdicion');
            if (!$idEdicionActual) {
                $idEdicionActual = request()->query('idEdicion') ?: request()->session()->get('panel.idEdicion');
            }

            $edicionSeleccionada = request()->attributes->get('panel.edicion');
            if (!$edicionSeleccionada && $idEdicionActual) {
                $edicionSeleccionada = $ediciones->firstWhere('id', (int) $idEdicionActual);
            }

            $view->with([
                'ediciones' => $ediciones,
                'EdicionSeleccionada' => $edicionSeleccionada,
                'idEdicionActual' => $idEdicionActual ? (int) $idEdicionActual : null,
            ]);
        });
    }
}
