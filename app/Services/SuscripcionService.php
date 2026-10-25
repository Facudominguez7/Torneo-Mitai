<?php

namespace App\Services;

use App\Mail\ConfirmacionSuscripcionMail;
use App\Models\Suscriptor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Alta, confirmación (doble opt-in) y baja de suscriptores a los avisos por correo.
 */
class SuscripcionService
{
    /**
     * Registra el pedido de suscripción. Un suscriptor activo no se modifica desde acá:
     * cualquiera puede escribir un correo ajeno, así que sus preferencias solo cambian con su enlace firmado.
     *
     * @param  list<int>  $idsEquipos
     * @param  list<string>|null  $avisos  null = todos los avisos
     * @return bool true si quedó activo en el momento (usuario con ese correo ya verificado)
     */
    public function suscribir(string $email, array $idsEquipos, ?array $avisos, ?User $usuario): bool
    {
        $suscriptor = Suscriptor::query()->firstOrNew(['email' => $email]);

        if ($suscriptor->exists && $suscriptor->estaActivo()) {
            return false;
        }

        $verificadoPorCuenta = $usuario !== null
            && mb_strtolower((string) $usuario->email) === $email
            && $usuario->hasVerifiedEmail();

        DB::transaction(function () use ($suscriptor, $idsEquipos, $avisos, $usuario, $verificadoPorCuenta): void {
            foreach (config('suscripciones.avisos') as $aviso => $columna) {
                $suscriptor->{$columna} = $avisos === null || in_array($aviso, $avisos, true);
            }
            $suscriptor->usuario_id = $verificadoPorCuenta ? $usuario->id : $suscriptor->usuario_id;
            $suscriptor->forceFill([
                'confirmado_at' => $verificadoPorCuenta ? now() : null,
                'baja_at' => null,
            ])->save();
            $suscriptor->equipos()->sync($idsEquipos);
        });

        if (! $verificadoPorCuenta) {
            // El mailer se elige acá: Mail::to()->send() pisa el que defina la mailable con el mailer por defecto.
            Mail::mailer(config('suscripciones.mailer'))->to($suscriptor->email)->send(new ConfirmacionSuscripcionMail($this->urlConfirmacion($suscriptor)));
        }

        return $verificadoPorCuenta;
    }

    public function confirmar(Suscriptor $suscriptor): void
    {
        if ($suscriptor->baja_at === null && $suscriptor->confirmado_at !== null) {
            return;
        }

        $suscriptor->forceFill(['confirmado_at' => now(), 'baja_at' => null])->save();
    }

    public function darDeBaja(Suscriptor $suscriptor): void
    {
        if ($suscriptor->baja_at === null) {
            $suscriptor->forceFill(['baja_at' => now()])->save();
        }
    }

    public function urlConfirmacion(Suscriptor $suscriptor): string
    {
        return URL::temporarySignedRoute(
            'suscripcion.confirmar',
            now()->addHours((int) config('suscripciones.horas_confirmacion')),
            $suscriptor,
        );
    }

    /** Enlace permanente para la baja en un clic (va en cada correo y en el encabezado List-Unsubscribe). */
    public function urlBaja(Suscriptor $suscriptor): string
    {
        return URL::signedRoute('suscripcion.baja', $suscriptor);
    }
}
