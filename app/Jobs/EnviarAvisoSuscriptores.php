<?php

namespace App\Jobs;

use App\Models\EnvioSuscriptores;
use App\Services\EnvioSuscriptoresService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Manda un aviso a los suscriptores ya registrado en la auditoría de envíos (cualquier tipo). */
class EnviarAvisoSuscriptores implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $envioId)
    {
        $this->afterCommit();
    }

    public function handle(EnvioSuscriptoresService $envios): void
    {
        $envio = EnvioSuscriptores::query()->find($this->envioId);

        if ($envio !== null) {
            $envios->procesar($envio);
        }
    }

    /** Agotados los intentos queda fallido: el panel permite reintentarlo. */
    public function failed(?Throwable $error): void
    {
        // Sin el mensaje del proveedor: puede incluir correos de suscriptores.
        Log::error('No se pudo enviar el aviso a los suscriptores.', [
            'envio_id' => $this->envioId,
            'error' => $error === null ? null : $error::class,
            'codigo' => $error?->getCode(),
        ]);

        EnvioSuscriptores::query()
            ->whereKey($this->envioId)
            ->where('estado', EnvioSuscriptores::ESTADO_PENDIENTE)
            ->update(['estado' => EnvioSuscriptores::ESTADO_FALLIDO]);
    }
}
