<?php

namespace App\Services;

use App\Jobs\EnviarAvisoSuscriptores;
use App\Mail\AvisoMigracionSuscriptoresMail;
use App\Mail\AvisoTorneoMail;
use App\Mail\ProximaFechaMail;
use App\Mail\ResumenResultadosFechaMail;
use App\Models\Edicion;
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
        return $this->registrar(
            EnvioSuscriptores::TIPO_RESUMEN_FECHA,
            EnvioSuscriptores::claveResumenFecha((int) $resumen['id_fecha'], $resumen['id_categoria']),
            ['idEdicion' => (int) $resumen['id_edicion'], 'idFecha' => (int) $resumen['id_fecha'], 'idCategoria' => $resumen['id_categoria']],
            $this->destinatariosResumenFecha(),
            $administrador,
        );
    }

    /**
     * Registra el envío de la próxima fecha y lo encola, con la misma regla que el resumen: cada
     * fecha y categoría sale una sola vez y solo se reintenta la que falló.
     *
     * @param  array<string, mixed>  $proximaFecha  armada por ResumenFechaService::armarProximaFecha
     * @return EnvioSuscriptores|null null si ya se envió o está en cola
     */
    public function registrarProximaFecha(array $proximaFecha, ?User $administrador): ?EnvioSuscriptores
    {
        return $this->registrar(
            EnvioSuscriptores::TIPO_PROXIMA_FECHA,
            EnvioSuscriptores::claveProximaFecha((int) $proximaFecha['id_fecha'], $proximaFecha['id_categoria']),
            ['idEdicion' => (int) $proximaFecha['id_edicion'], 'idFecha' => (int) $proximaFecha['id_fecha'], 'idCategoria' => $proximaFecha['id_categoria']],
            $this->destinatarios('proxima_fecha'),
            $administrador,
        );
    }

    /** El envío registrado de la próxima fecha de esa fecha y categoría, si existe. */
    public function envioProximaFecha(int $idFecha, ?int $idCategoria): ?EnvioSuscriptores
    {
        return $this->envio(EnvioSuscriptores::claveProximaFecha($idFecha, $idCategoria));
    }

    /**
     * Registra un aviso libre del torneo con su título y mensaje y lo encola. El mismo identificador
     * de envío no se vuelve a mandar; uno que falló sí se puede reintentar.
     *
     * @return EnvioSuscriptores|null null si ese aviso ya se envió o está en cola
     */
    public function registrarAvisoTorneo(string $idEnvio, string $titulo, string $mensaje, ?User $administrador): ?EnvioSuscriptores
    {
        return $this->registrar(
            EnvioSuscriptores::TIPO_AVISOS,
            EnvioSuscriptores::claveAvisoTorneo($idEnvio),
            [
                // La columna no admite nulos: queda la edición en curso cuando salió el aviso.
                'idEdicion' => (int) (Edicion::query()->latest('id')->value('id') ?? 0),
                'contenido' => ['titulo' => $titulo, 'mensaje' => $mensaje],
            ],
            $this->destinatarios('avisos'),
            $administrador,
        );
    }

    /**
     * Registra el aviso único de la migración a todos los suscriptores activos y lo encola.
     *
     * @return EnvioSuscriptores|null null si ya se envió o está en cola
     */
    public function registrarAvisoMigracion(?User $administrador = null): ?EnvioSuscriptores
    {
        return $this->registrar(
            EnvioSuscriptores::TIPO_AVISO_MIGRACION,
            EnvioSuscriptores::CLAVE_AVISO_MIGRACION,
            // La columna no admite nulos: queda la edición en curso cuando salió el aviso.
            ['idEdicion' => (int) (Edicion::query()->latest('id')->value('id') ?? 0)],
            $this->destinatarios(null),
            $administrador,
        );
    }

    /**
     * Registra un aviso en la auditoría y lo encola. La clave única es el candado: un aviso con la
     * misma clave que ya salió o está en cola no se vuelve a enviar; uno que falló sí se puede reintentar.
     *
     * @param  array<string, mixed>  $atributos  idEdicion, idFecha, idCategoria del aviso
     * @return EnvioSuscriptores|null null si ese aviso ya se envió o está en cola
     */
    public function registrar(string $tipo, string $clave, array $atributos, int $destinatarios, ?User $administrador): ?EnvioSuscriptores
    {
        $datos = [
            'usuario_id' => $administrador?->id,
            'estado' => EnvioSuscriptores::ESTADO_PENDIENTE,
            'destinatarios' => $destinatarios,
        ];

        try {
            $envio = EnvioSuscriptores::query()->create([...$datos, ...$atributos, 'tipo' => $tipo, 'clave' => $clave]);
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
            EnviarAvisoSuscriptores::dispatch($envio->id);
        } catch (Throwable $error) {
            // Sin job en la cola nadie lo mandaría: queda fallido para poder pedirlo de nuevo.
            $envio->forceFill(['estado' => EnvioSuscriptores::ESTADO_FALLIDO])->save();

            throw $error;
        }

        return $envio;
    }

    /** El envío registrado con esa clave, si existe. */
    public function envio(string $clave): ?EnvioSuscriptores
    {
        return EnvioSuscriptores::query()->where('clave', $clave)->first();
    }

    /** El envío registrado del resumen de esa fecha y categoría, si existe. */
    public function envioResumenFecha(int $idFecha, ?int $idCategoria): ?EnvioSuscriptores
    {
        return $this->envio(EnvioSuscriptores::claveResumenFecha($idFecha, $idCategoria));
    }

    /** Suscriptores que reciben el resumen de cada fecha. */
    public function destinatariosResumenFecha(): int
    {
        return $this->destinatarios('resumen_fecha');
    }

    /** Activos que eligieron ese aviso (clave de config('suscripciones.avisos')); null = todos los activos. */
    public function destinatarios(?string $aviso): int
    {
        return Suscriptor::query()->recibenAviso($aviso)->count();
    }

    /**
     * Lo corre el job: arma el contenido del aviso en el momento y lo entrega por el canal. Para sumar
     * un tipo de aviso alcanza con su TIPO_* y su rama acá; los canales no cambian.
     */
    public function procesar(EnvioSuscriptores $envio): void
    {
        if ($envio->estado !== EnvioSuscriptores::ESTADO_PENDIENTE) {
            return;
        }

        $destinatarios = match ($envio->tipo) {
            EnvioSuscriptores::TIPO_RESUMEN_FECHA => $this->enviarResumenFecha($envio),
            EnvioSuscriptores::TIPO_PROXIMA_FECHA => $this->enviarProximaFecha($envio),
            EnvioSuscriptores::TIPO_AVISOS => $this->canal->enviar(
                $envio,
                'avisos',
                fn (string $urlPreferencias): AvisoTorneoMail => new AvisoTorneoMail(
                    (string) ($envio->contenido['titulo'] ?? ''),
                    (string) ($envio->contenido['mensaje'] ?? ''),
                    $urlPreferencias,
                ),
                'emails.suscripcion.aviso-torneo-text',
            ),
            EnvioSuscriptores::TIPO_AVISO_MIGRACION => $this->canal->enviar(
                $envio,
                null,
                fn (string $urlPreferencias): AvisoMigracionSuscriptoresMail => new AvisoMigracionSuscriptoresMail($urlPreferencias),
                'emails.suscripcion.aviso-migracion-text',
            ),
        };

        if ($destinatarios === null) {
            $envio->forceFill(['estado' => EnvioSuscriptores::ESTADO_FALLIDO])->save();

            return;
        }

        $envio->forceFill([
            'estado' => EnvioSuscriptores::ESTADO_ENVIADO,
            'destinatarios' => $destinatarios,
            'enviado_at' => now(),
        ])->save();
    }

    /** Arma el resumen con los resultados del momento; null si la fecha ya no tiene resultados. */
    private function enviarResumenFecha(EnvioSuscriptores $envio): ?int
    {
        $resumen = $this->resumenes->armar($envio->idEdicion, (int) $envio->idFecha, $envio->idCategoria);

        if ($resumen === null) {
            return null;
        }

        return $this->canal->enviar(
            $envio,
            'resumen_fecha',
            fn (string $urlPreferencias): ResumenResultadosFechaMail => new ResumenResultadosFechaMail($resumen, $urlPreferencias),
            'emails.resumen-resultados-fecha-text',
        );
    }

    /** Arma la próxima fecha con la programación del momento; null si ya no quedan partidos por jugar. */
    private function enviarProximaFecha(EnvioSuscriptores $envio): ?int
    {
        $proximaFecha = $this->resumenes->armarProximaFecha($envio->idEdicion, (int) $envio->idFecha, $envio->idCategoria);

        if ($proximaFecha === null) {
            return null;
        }

        return $this->canal->enviar(
            $envio,
            'proxima_fecha',
            fn (string $urlPreferencias): ProximaFechaMail => new ProximaFechaMail($proximaFecha, $urlPreferencias),
            'emails.suscripcion.proxima-fecha-text',
        );
    }
}
