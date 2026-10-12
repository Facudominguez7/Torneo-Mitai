<?php

namespace App\Providers;

use App\Listeners\LogNotificacionEnviada;
use App\Models\Edicion;
use App\Services\EmailDeliverabilityHeaders;
use App\View\Composers\NavigationComposer;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event as FacadesEvent;
use Illuminate\Support\Facades\URL;
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
     */
    public function boot(): void
    {
        // Registrar listener para logear emails enviados
        FacadesEvent::listen(
            NotificationSent::class,
            LogNotificacionEnviada::class
        );

        // Personalizar URL de verificación para usar query parameters (evita 403 Forbidden)
        VerifyEmail::createUrlUsing(function (object $notifiable) {
            return URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );
        });

        VerifyEmail::toMailUsing(function (object $notifiable, string $verificationUrl) {
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

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        /**
         * Compartir contexto global del panel en el layout base.
         *
         * Evita que cada controlador tenga que enviar siempre `ediciones`
         * y `EdicionSeleccionada` manualmente.
         */
        /**
         * Aportar la edición más reciente a la navegación pública cuando la
         * vista que incluye el partial no la provee. Nunca pisa
         * `EdicionSeleccionada` ni un `ultimaEdicion` ya presente.
         */
        View::composer('layouts.navigation', NavigationComposer::class);

        View::composer('Panel.admin', function (ViewContract $view): void {
            $ediciones = Edicion::orderByDesc('id')->get();

            $request = request();

            // Si el middleware ya resolvió el contexto (atributo presente,
            // incluso null), se confía en él; el fallback a query/sesión
            // aplica solo para render directo de la vista sin middleware.
            $idEdicionActual = $request->attributes->has('panel.idEdicion')
                ? $request->attributes->get('panel.idEdicion')
                : ($request->query('idEdicion') ?: $request->session()->get('panel.idEdicion'));

            $edicionSeleccionada = request()->attributes->get('panel.edicion');
            if (! $edicionSeleccionada && $idEdicionActual) {
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
