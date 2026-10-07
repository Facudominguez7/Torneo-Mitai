<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * Cuota histórica de planilla de un equipo por partido (regular, interzonal
 * o instancia final). El snapshot conserva los nombres de la época
 * para sobrevivir a borrados.
 *
 * @property int $id
 * @property string $tipo planilla_partido|planilla_instancia_final
 * @property int $parent_id
 * @property string $parent_type
 * @property int $idEquipo
 * @property int $idEdicion
 * @property int $idCategoria
 * @property int $precio_original Importe original congelado en ARS (INT firmado).
 * @property array<string, mixed> $snapshot
 */
class CargoPlanilla extends Model
{
    public const TIPO_PARTIDO = 'planilla_partido';

    public const TIPO_INSTANCIA_FINAL = 'planilla_instancia_final';

    /**
     * Whitelist financiera: tipo de cargo => clase del padre esperada.
     *
     * @var array<string, string>
     */
    public const TIPOS_FINANCIEROS = [
        self::TIPO_PARTIDO => Partido::class,
        self::TIPO_INSTANCIA_FINAL => InstanciaFinal::class,
    ];

    /** @var array<int, string> */
    protected $fillable = [
        'tipo', 'parent_id', 'parent_type', 'idEquipo', 'idEdicion', 'idCategoria', 'precio_original', 'snapshot',
    ];

    /**
     * Obtener los atributos que deben ser casteados.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio_original' => 'integer',
            'snapshot' => 'array',
        ];
    }

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'idEquipo');
    }

    public function edicion(): BelongsTo
    {
        return $this->belongsTo(Edicion::class, 'idEdicion');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'idCategoria');
    }

    /**
     * El tipo debe pertenecer a la whitelist financiera y coincidir
     * con el padre antes de insertar un importe entero válido.
     * No protege contra QueryBuilder/SQL directo.
     */
    protected static function booted(): void
    {
        static::creating(function (CargoPlanilla $cargo): void {
            $cargo->exigirTipoFinancieroValido();

            $precio = $cargo->getAttributes()['precio_original'] ?? null;
            if (! is_int($precio) || $precio < 1 || $precio > 2147483647) {
                throw new RuntimeException('El precio del cargo debe ser un entero positivo en ARS dentro del rango SQL.');
            }
        });
    }

    private function exigirTipoFinancieroValido(): void
    {
        $claseEsperada = self::TIPOS_FINANCIEROS[$this->tipo] ?? null;

        if ($claseEsperada === null) {
            throw new RuntimeException(sprintf('El tipo de cargo "%s" no pertenece a la whitelist financiera.', (string) $this->tipo));
        }

        if ($this->parent_type !== null && $this->parent_type !== $claseEsperada) {
            throw new RuntimeException(sprintf('El tipo de cargo "%s" no corresponde al padre "%s".', (string) $this->tipo, (string) $this->parent_type));
        }
    }
}
