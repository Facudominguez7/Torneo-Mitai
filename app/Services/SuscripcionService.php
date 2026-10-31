<?php

namespace App\Services;

use App\Jobs\SincronizarSuscriptorResend;
use App\Mail\ConfirmacionSuscripcionMail;
use App\Models\Suscriptor;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

/**
 * Alta, confirmación (doble opt-in) y baja de suscriptores a los avisos por correo.
 */
class SuscripcionService
{
    /**
     * Registra el pedido de suscripción. Un suscriptor activo no se modifica desde acá:
     * cualquiera puede escribir un correo ajeno, así que sus preferencias solo cambian con su enlace firmado.
     * Por la misma razón, una baja sigue vigente hasta que se confirme el pedido nuevo.
     *
     * @param  list<string>|null  $avisos  null = todos los avisos
     * @return bool true si quedó activo en el momento (usuario con ese correo ya verificado)
     */
    public function suscribir(string $email, ?array $avisos, ?User $usuario): bool
    {
        $suscriptor = Suscriptor::query()->firstOrNew(['email' => $email]);

        if ($suscriptor->exists && $suscriptor->estaActivo()) {
            return false;
        }

        $verificadoPorCuenta = $usuario !== null
            && mb_strtolower((string) $usuario->email) === $email
            && $usuario->hasVerifiedEmail();

        $claveEspera = 'suscripcion-confirmacion:'.sha1($email);
        if (! $verificadoPorCuenta && RateLimiter::tooManyAttempts($claveEspera, 1)) {
            return false;
        }

        DB::transaction(function () use ($suscriptor, $avisos, $usuario, $verificadoPorCuenta): void {
            foreach (config('suscripciones.avisos') as $aviso => $columna) {
                $suscriptor->{$columna} = $avisos === null || in_array($aviso, $avisos, true);
            }
            if ($verificadoPorCuenta) {
                $suscriptor->usuario_id = $usuario->id;
                $suscriptor->forceFill(['confirmado_at' => now(), 'baja_at' => null, 'resend_sincronizado_at' => null, 'cambio_local_ms' => now()->getTimestampMs()]);
            }
            $suscriptor->save();
        });

        if (! $verificadoPorCuenta) {
            // El mailer se elige acá: Mail::to()->send() pisa el que defina la mailable con el mailer por defecto.
            // La mailable es ShouldQueue, así que send() la encola con ese mailer.
            Mail::mailer(config('suscripciones.mailer'))->to($suscriptor->email)->send(new ConfirmacionSuscripcionMail($this->urlConfirmacion($suscriptor)));
            RateLimiter::hit($claveEspera, (int) config('suscripciones.minutos_entre_confirmaciones') * 60);
        } else {
            SincronizarSuscriptorResend::dispatch($suscriptor->id);
        }

        return $verificadoPorCuenta;
    }

    /**
     * @param  int  $emitido  momento (timestamp) en que se generó el enlace
     * @return bool false si el enlace es anterior a la baja: esa baja no se deshace con un correo viejo
     */
    public function confirmar(Suscriptor $suscriptor, int $emitido): bool
    {
        if ($suscriptor->baja_at !== null && $suscriptor->baja_at->getTimestamp() >= $emitido) {
            return false;
        }

        if (! $suscriptor->estaActivo()) {
            $suscriptor->forceFill(['confirmado_at' => now(), 'baja_at' => null, 'resend_sincronizado_at' => null, 'cambio_local_ms' => now()->getTimestampMs()])->save();
            SincronizarSuscriptorResend::dispatch($suscriptor->id);
        }

        return true;
    }

    /**
     * Cambia los avisos desde el enlace firmado del correo, que prueba que el correo es suyo:
     * por eso guardar también reactiva a quien se había dado de baja.
     *
     * @param  list<string>  $avisos
     */
    public function actualizarPreferencias(Suscriptor $suscriptor, array $avisos): void
    {
        DB::transaction(function () use ($suscriptor, $avisos): void {
            foreach (config('suscripciones.avisos') as $aviso => $columna) {
                $suscriptor->{$columna} = in_array($aviso, $avisos, true);
            }
            $suscriptor->forceFill([
                'confirmado_at' => $suscriptor->confirmado_at ?? now(),
                'baja_at' => null,
                'resend_sincronizado_at' => null, 'cambio_local_ms' => now()->getTimestampMs(),
            ])->save();
        });

        SincronizarSuscriptorResend::dispatch($suscriptor->id);
    }

    public function darDeBaja(Suscriptor $suscriptor): void
    {
        if ($suscriptor->baja_at === null) {
            $suscriptor->forceFill(['baja_at' => now(), 'resend_sincronizado_at' => null, 'cambio_local_ms' => now()->getTimestampMs()])->save();
            SincronizarSuscriptorResend::dispatch($suscriptor->id);
        }
    }

    public function urlConfirmacion(Suscriptor $suscriptor): string
    {
        return URL::temporarySignedRoute(
            'suscripcion.confirmar',
            now()->addHours((int) config('suscripciones.horas_confirmacion')),
            ['suscriptor' => $suscriptor, 'emitido' => now()->getTimestamp()],
        );
    }

    /** Enlace permanente a las preferencias (va en cada correo). */
    public function urlPreferencias(Suscriptor $suscriptor): string
    {
        return URL::signedRoute('suscripcion.preferencias', $suscriptor);
    }

    /**
     * Enlace permanente de baja: en el pie de cada correo abre la página que pide confirmar (GET)
     * y en el encabezado List-Unsubscribe da de baja en un clic (POST).
     */
    public function urlBaja(Suscriptor $suscriptor): string
    {
        return URL::signedRoute('suscripcion.baja', $suscriptor);
    }

    /**
     * Aplica un cambio que el suscriptor hizo desde Resend, sin encolar otra sincronización: Resend ya lo tiene.
     * Se ignora si la persona cambió algo en el sitio después del evento: ese cambio local es el vigente.
     * Se compara con cambio_local_ms y no con updated_at, que también mueven la sincronización y otros eventos.
     *
     * @param  array<string, mixed>  $datos  campo data del evento
     */
    public function aplicarCambioDesdeResend(string $tipo, array $datos, CarbonInterface $emitido): void
    {
        $email = mb_strtolower(trim((string) ($datos['email'] ?? '')));
        $suscriptor = $email === '' ? null : Suscriptor::query()->where('email', $email)->first();

        if ($suscriptor === null || ($suscriptor->cambio_local_ms !== null && $suscriptor->cambio_local_ms >= $emitido->getTimestampMs())) {
            return;
        }

        $cambios = match ($tipo) {
            'contact.deleted' => ['resend_contacto_id' => null, 'baja_at' => $suscriptor->baja_at ?? $emitido],
            'contact.updated' => ($datos['unsubscribed'] ?? false) === true ? ['baja_at' => $suscriptor->baja_at ?? $emitido] : [],
            'contact.topics.updated' => $this->avisosDesdeTopicos($datos['topics'] ?? []),
            default => [],
        };

        // resend_sincronizado_at no se toca: si había un cambio local pendiente, se sigue enviando
        // ya combinado con este; si no, Resend ya quedó igual que acá.
        if ($cambios !== []) {
            $suscriptor->forceFill($cambios)->save();
        }
    }

    /**
     * @param  mixed  $topicos  lista de {id, subscription} con los tópicos que cambiaron
     * @return array<string, bool> columna del aviso => si lo recibe
     */
    private function avisosDesdeTopicos(mixed $topicos): array
    {
        $avisoPorTopico = array_flip(array_map('strval', config('suscripciones.resend.topicos')));
        $cambios = [];

        foreach (is_array($topicos) ? $topicos : [] as $topico) {
            $aviso = $avisoPorTopico[(string) ($topico['id'] ?? '')] ?? null;
            $columna = $aviso === null ? null : config("suscripciones.avisos.{$aviso}");

            if ($columna !== null && in_array($topico['subscription'] ?? null, ['opt_in', 'opt_out'], true)) {
                $cambios[$columna] = $topico['subscription'] === 'opt_in';
            }
        }

        return $cambios;
    }
}
