<?php

namespace App\Services;

use App\Jobs\EnviarResumenFechaSuscriptores;
use App\Models\EnvioSuscriptores;
use App\Models\Suscriptor;
use App\Models\User;
use App\Services\EnvioSuscriptores\CanalEnvioSuscriptores;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

/**
 * Avisos masivos a los suscriptores. El pedido del panel solo registra el envío en la auditoría y
 * lo encola; el job lo procesa y lo entrega por el canal del entorno (ver CanalEnvioSuscriptores).
 */
class EnvioSuscriptoresService
{
    public function __construct(
        private readonly ResumenFechaService $resumenes,
        private readonly CanalEnvioSuscriptores $canal,
    ) {}

    /**
     * Registra el envío del resumen y lo encola. Un resumen de la misma fecha y categoría que ya
     * salió o está en cola no se vuelve a enviar; uno que falló sí se puede reintentar.
     *
     * @param  array<string, mixed>  $resumen  armado por ResumenFechaService
     * @return EnvioSuscriptores|null null si ese resumen ya se envió o está en cola
     */
    public function registrarResumenFecha(array $resumen, ?User $administrador): ?EnvioSuscriptores
    {
        $clave = EnvioSuscriptores::claveResumenFecha((int) $resumen['id_fecha'], $resumen['id_categoria']);
        $datos = [
            'usuario_id' => $administrador?->id,
            'estado' => EnvioSuscriptores::ESTADO_PENDIENTE,
            'destinatarios' => $this->destinatariosResumenFecha(),
        ];

        try {
            $envio = EnvioSuscriptores::query()->create([
                ...$datos,
                'tipo' => EnvioSuscriptores::TIPO_RESUMEN_FECHA,
                'clave' => $clave,
                'idEdicion' => (int) $resumen['id_edicion'],
                'idFecha' => (int) $resumen['id_fecha'],
                'idCategoria' => $resumen['id_categoria'],
            ]);
        } catch (UniqueConstraintViolationException) {
            // Solo un pedido puede reactivar el que falló: el update condicionado es atómico.
            $reactivados = EnvioSuscriptores::query()
                ->where('clave', $clave)
                ->where('estado', EnvioSuscriptores::ESTADO_FALLIDO)
                ->update($datos);

            if ($reactivados === 0) {
                return null;
            }

            $envio = EnvioSuscriptores::query()->where('clave', $clave)->firstOrFail();
        }

        try {
            EnviarResumenFechaSuscriptores::dispatch($envio->id);
        } catch (Throwable $error) {
            // Sin job en la cola nadie lo mandaría: queda fallido para poder pedirlo de nuevo.
            $envio->forceFill(['estado' => EnvioSuscriptores::ESTADO_FALLIDO])->save();

            throw $error;
        }

        return $envio;
    }

    /** El envío registrado del resumen de esa fecha y categoría, si existe. */
    public function envioResumenFecha(int $idFecha, ?int $idCategoria): ?EnvioSuscriptores
    {
        return EnvioSuscriptores::query()->where('clave', EnvioSuscriptores::claveResumenFecha($idFecha, $idCategoria))->first();
    }

    /** Suscriptores que reciben el resumen de cada fecha. */
    public function destinatariosResumenFecha(): int
    {
        return Suscriptor::query()->recibenResumenFecha()->count();
    }

    /**
     * Lo corre el job: arma el resumen con los resultados del momento y lo entrega por el canal.
     */
    public function procesarResumenFecha(EnvioSuscriptores $envio): void
    {
        if ($envio->estado !== EnvioSuscriptores::ESTADO_PENDIENTE) {
            return;
        }

        $resumen = $this->resumenes->armar($envio->idEdicion, (int) $envio->idFecha, $envio->idCategoria);

        if ($resumen === null) {
            $envio->forceFill(['estado' => EnvioSuscriptores::ESTADO_FALLIDO])->save();

            return;
        }

        $destinatarios = $this->canal->enviarResumenFecha($envio, $resumen);

        $envio->forceFill([
            'estado' => EnvioSuscriptores::ESTADO_ENVIADO,
            'destinatarios' => $destinatarios,
            'enviado_at' => now(),
        ])->save();
    }
}
