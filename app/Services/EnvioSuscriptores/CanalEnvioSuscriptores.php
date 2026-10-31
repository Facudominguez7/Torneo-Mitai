<?php

namespace App\Services\EnvioSuscriptores;

use App\Models\EnvioSuscriptores;

/**
 * Último tramo de un aviso masivo: cómo llega a los suscriptores. Registro, cola, job y estados
 * son los mismos en todos los entornos; solo cambia el canal (Resend en producción, MailHog en local).
 */
interface CanalEnvioSuscriptores
{
    /**
     * Manda el resumen ya armado. Lo llama el job y puede reintentarse.
     *
     * @param  array<string, mixed>  $resumen  armado por ResumenFechaService
     * @return int destinatarios
     */
    public function enviarResumenFecha(EnvioSuscriptores $envio, array $resumen): int;
}
