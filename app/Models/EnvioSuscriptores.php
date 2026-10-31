<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un aviso masivo a los suscriptores: quién lo pidió, a cuántos llegó y en qué quedó. */
class EnvioSuscriptores extends Model
{
    public const TIPO_RESUMEN_FECHA = 'resumen_fecha';

    /** Aviso único a los usuarios que se pasaron a la lista de suscriptores: su clave es fija. */
    public const TIPO_AVISO_MIGRACION = 'aviso_migracion';

    public const CLAVE_AVISO_MIGRACION = self::TIPO_AVISO_MIGRACION;

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_ENVIADO = 'enviado';

    public const ESTADO_FALLIDO = 'fallido';

    protected $table = 'envios_suscriptores';

    protected $fillable = [
        'tipo', 'clave', 'idEdicion', 'idFecha', 'idCategoria', 'usuario_id', 'estado', 'destinatarios', 'broadcast_id', 'enviado_at',
    ];

    protected function casts(): array
    {
        return [
            'idEdicion' => 'integer',
            'idFecha' => 'integer',
            'idCategoria' => 'integer',
            'destinatarios' => 'integer',
            'enviado_at' => 'datetime',
        ];
    }

    public static function claveResumenFecha(int $idFecha, ?int $idCategoria): string
    {
        return self::TIPO_RESUMEN_FECHA.':'.$idFecha.':'.($idCategoria ?? 'todas');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
