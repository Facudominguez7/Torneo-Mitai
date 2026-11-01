<?php

namespace App\Jobs;

use App\Models\Suscriptor;
use App\Services\SincronizacionResendService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Lleva a Resend el estado actual de un suscriptor. Solo carga el id: al procesarse lee los datos
 * del momento, así que varios cambios seguidos se resuelven con una única sincronización.
 */
class SincronizarSuscriptorResend implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Segundos de espera antes de cada reintento: Resend puede estar caído un rato. */
    public const ESPERAS = [60, 300, 1800, 1800];

    public int $tries = 5;

    public function __construct(public readonly int $suscriptorId)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->suscriptorId;
    }

    public function handle(SincronizacionResendService $sincronizacion): void
    {
        $suscriptor = Suscriptor::query()->find($this->suscriptorId);

        if ($suscriptor === null || $suscriptor->confirmado_at === null || $suscriptor->resend_sincronizado_at !== null) {
            return;
        }

        if (! config('suscripciones.resend.sincronizar')) {
            return;
        }

        if (! $sincronizacion->sincronizar($suscriptor)) {
            // Al agotar los intentos queda en failed_jobs y el comando horario lo vuelve a tomar.
            $this->release(self::ESPERAS[min($this->attempts(), count(self::ESPERAS)) - 1]);
        }
    }
}
