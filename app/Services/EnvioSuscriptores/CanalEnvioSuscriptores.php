<?php

namespace App\Services\EnvioSuscriptores;

use App\Models\EnvioSuscriptores;
use Closure;
use Illuminate\Mail\Mailable;

/**
 * Último tramo de un aviso masivo: cómo llega a los suscriptores. Registro, cola, job y estados
 * son los mismos en todos los entornos; solo cambia el canal (Resend en producción, MailHog en local).
 */
interface CanalEnvioSuscriptores
{
    /**
     * Manda un aviso ya registrado a los suscriptores activos que lo eligieron. Lo llama el job y
     * puede reintentarse sin mandarlo dos veces.
     *
     * @param  string|null  $aviso  clave de config('suscripciones.avisos'); null = todos los activos (el segmento entero)
     * @param  Closure(string): Mailable  $correo  arma el correo con el enlace de preferencias que recibe
     * @param  string  $vistaTexto  versión de texto plano; recibe los datos de la vista del correo
     * @return int destinatarios
     */
    public function enviar(EnvioSuscriptores $envio, ?string $aviso, Closure $correo, string $vistaTexto): int;
}
