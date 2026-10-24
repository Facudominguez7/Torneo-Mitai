<?php

namespace App\Models;

use App\Services\Cobros\DistribucionCreditoService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use RuntimeException;

/**
 * Porción histórica de un ingreso destinada a un cargo, no un nuevo ingreso.
 * Creación interna: valida deuda efectiva agregada, pero no disponibilidad del pago.
 * Los servicios coordinan disponibilidad, autorización y auditoría bajo bloqueos.
 * La guarda sola no garantiza concurrencia ni protege SQL directo o eventos deshabilitados.
 */
class AplicacionPagoPlanilla extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['pago_id', 'cargo_id', 'importe_ars'];

    protected function casts(): array
    {
        return [
            'pago_id' => 'integer', 'cargo_id' => 'integer', 'importe_ars' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(PagoPlanilla::class, 'pago_id');
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(CargoPlanilla::class, 'cargo_id');
    }

    protected static function booted(): void
    {
        static::creating(function (AplicacionPagoPlanilla $aplicacion): void {
            $atributos = $aplicacion->getAttributes();
            foreach (['pago_id', 'cargo_id', 'importe_ars'] as $campo) {
                $valor = $atributos[$campo] ?? null;
                if (! is_int($valor) || $valor < 1 || ($campo === 'importe_ars' && $valor > 2147483647)) {
                    throw new LogicException('La aplicación requiere identificadores enteros positivos e importe ARS dentro de INT firmado.');
                }
            }

            $pago = PagoPlanilla::query()->with('cuenta')->find($aplicacion->pago_id);
            $cargo = CargoPlanilla::query()->find($aplicacion->cargo_id);
            $cuenta = $pago?->cuenta;
            if (! $pago || ! $cargo || ! $cuenta
                || (int) $cargo->idEquipo !== $cuenta->idEquipo
                || (int) $cargo->idEdicion !== $cuenta->idEdicion) {
                throw new LogicException('La aplicación requiere un pago y un cargo del mismo equipo y edición.');
            }

            if ($aplicacion->importe_ars > $pago->importe_ars || $aplicacion->importe_ars > $cargo->precio_original) {
                throw new LogicException('Una porción no puede superar el ingreso original ni el precio original del cargo.');
            }

            $exencion = ExencionCobroPlanilla::query()->where('cargo_id', (int) $cargo->id)->first();
            try {
                $pendiente = (new DistribucionCreditoService)->calcularDeuda([
                    'precio_original' => (int) $cargo->precio_original,
                    'aplicado_ars' => (int) static::query()->where('cargo_id', (int) $cargo->id)->sum('importe_ars'),
                    'eximido_ars' => $exencion === null ? 0 : $exencion->importe_ars,
                    'ausente' => AusenciaCobroPlanilla::query()->vigentes()->where('cargo_id', (int) $cargo->id)->exists(),
                ], (int) $cargo->id);
            } catch (RuntimeException $error) {
                throw new LogicException('El cargo registra deuda efectiva corrupta.', 0, $error);
            }
            if ($aplicacion->importe_ars > $pendiente) {
                throw new LogicException('La porción no puede superar la deuda efectiva pendiente del cargo.');
            }

            $aplicacion->setCreatedAt(now());
        });

        static::updating(function (): void {
            throw new LogicException('Las aplicaciones de pagos no se pueden editar.');
        });

        static::deleting(function (): void {
            throw new LogicException('Las aplicaciones se conservan para la historia financiera.');
        });
    }
}
