<?php

namespace App\Services;

use App\Models\Suscriptor;
use Illuminate\Support\Carbon;

/**
 * Resumen de las invitaciones diarias a suscribirse: cómo vienen y cuántos usuarios faltan invitar.
 */
class InvitacionesSuscripcionService
{
    /** Tasa de rebote que admite Resend: por encima de ella puede suspender los envíos. */
    public const TASA_REBOTE_MAXIMA = 4.0;

    public function __construct(private readonly SuscripcionService $suscripciones) {}

    /**
     * Cuenta las invitaciones por estado en una sola consulta agregada y suma los candidatos que faltan.
     * Los suscriptores que se dieron de alta desde el sitio no son invitaciones y no entran en la cuenta.
     *
     * @return array{
     *     invitados: int,
     *     confirmados: int,
     *     pendientes: int,
     *     vencidos: int,
     *     rebotes: int,
     *     quejas: int,
     *     tasa_rebote: float|null,
     *     alerta_rebote: bool,
     *     tasa_rebote_maxima: float,
     *     horas_confirmacion: int,
     *     faltan_invitar: int,
     *     hora_lote: string,
     *     invitaciones_por_lote: int,
     * }
     */
    public function resumen(): array
    {
        $horasConfirmacion = (int) config('suscripciones.horas_confirmacion');
        $limiteEnlace = Carbon::now()->subHours($horasConfirmacion)->toDateTimeString();

        $totales = Suscriptor::query()
            ->whereNotNull('invitado_at')
            ->selectRaw('COUNT(*) AS invitados')
            ->selectRaw('COUNT(CASE WHEN confirmado_at IS NOT NULL THEN 1 END) AS confirmados')
            ->selectRaw('COUNT(CASE WHEN confirmado_at IS NULL AND invitado_at >= ? THEN 1 END) AS pendientes', [$limiteEnlace])
            ->selectRaw('COUNT(CASE WHEN confirmado_at IS NULL AND invitado_at < ? THEN 1 END) AS vencidos', [$limiteEnlace])
            ->selectRaw('COUNT(rebote_at) AS rebotes')
            ->selectRaw('COUNT(queja_at) AS quejas')
            ->toBase()
            ->first();

        $invitados = (int) ($totales->invitados ?? 0);
        $rebotes = (int) ($totales->rebotes ?? 0);
        // Sin invitados no hay tasa: la sección muestra un guion en lugar de dividir por cero.
        $tasaRebote = $invitados === 0 ? null : round($rebotes * 100 / $invitados, 1);

        return [
            'invitados' => $invitados,
            'confirmados' => (int) ($totales->confirmados ?? 0),
            'pendientes' => (int) ($totales->pendientes ?? 0),
            'vencidos' => (int) ($totales->vencidos ?? 0),
            'rebotes' => $rebotes,
            'quejas' => (int) ($totales->quejas ?? 0),
            'tasa_rebote' => $tasaRebote,
            'alerta_rebote' => $tasaRebote !== null && $tasaRebote >= self::TASA_REBOTE_MAXIMA,
            'tasa_rebote_maxima' => self::TASA_REBOTE_MAXIMA,
            'horas_confirmacion' => $horasConfirmacion,
            'faltan_invitar' => count($this->suscripciones->usuariosParaInvitar()),
            'hora_lote' => (string) config('suscripciones.invitaciones.hora'),
            'invitaciones_por_lote' => (int) config('suscripciones.invitaciones.lote_diario'),
        ];
    }
}
