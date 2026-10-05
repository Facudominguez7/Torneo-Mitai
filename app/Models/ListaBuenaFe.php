<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo ListaBuenaFe
 *
 * Representa el registro de jugadores en la Lista de Buena Fe de un equipo.
 * La lista de buena fe es el listado oficial de jugadores habilitados para
 * participar en una edición del torneo con un equipo específico.
 *
 * Incluye funcionalidad para:
 * - Gestionar altas y bajas de jugadores
 * - Rastrear conflictos administrativos y su resolución
 * - Verificar suspensiones por tarjetas rojas
 *
 * @author Sistema de Gestión Torneo Mitai
 */
class ListaBuenaFe extends Model
{
    use HasFactory;

    protected $table = 'lista_buena_fe';

    /**
     * Campos que pueden ser asignados masivamente
     *
     * Incluye campos de identificación del jugador, información temporal,
     * estado de actividad, y datos relacionados con conflictos administrativos
     * y su resolución.
     */
    protected $fillable = [
        'dni_jugador',           // DNI del jugador (FK a tabla jugadores)
        'idEquipo',              // ID del equipo (FK a tabla equipos)
        'idEdicion',             // ID de la edición del torneo (FK a tabla ediciones)
        'idCategoria',           // ID de la categoría (FK a tabla categorias)
        'fecha_nacimiento',      // Fecha de nacimiento del jugador
        'fecha_alta',            // Fecha en que se agregó a la lista
        'fecha_baja',            // Fecha en que se dio de baja (nullable)
        'motivo_baja',           // Razón de la baja (nullable)
        'activo',                // Boolean: si el jugador está activo en la lista
        'conflictivo',           // Boolean: si tiene conflicto administrativo
        'motivo_conflicto',      // Descripción del conflicto (nullable)
        'resuelto',              // Boolean: si el conflicto fue resuelto
        'fecha_resolucion',      // Timestamp de resolución del conflicto (nullable)
        'resuelto_por',          // Usuario que resolvió el conflicto (nullable)
        'notas_resolucion',      // Notas sobre la resolución del conflicto (nullable)
    ];

    /**
     * Conversión automática de tipos para atributos
     *
     * Laravel automáticamente convertirá estos campos a los tipos especificados
     * cuando se acceda a ellos o se guarden en la base de datos.
     */
    protected $casts = [
        'fecha_alta' => 'datetime',        // Convierte a objeto Carbon
        'fecha_baja' => 'datetime',        // Convierte a objeto Carbon
        'fecha_nacimiento' => 'date',      // Convierte a objeto Carbon (solo fecha)
        'fecha_resolucion' => 'datetime',  // Convierte a objeto Carbon
        'activo' => 'boolean',             // Convierte 0/1 a true/false
        'conflictivo' => 'boolean',        // Convierte 0/1 a true/false
        'resuelto' => 'boolean',           // Convierte 0/1 a true/false
    ];

    /**
     * Relación con el modelo Jugador
     *
     * Un registro de lista de buena fe pertenece a un jugador.
     * Se usa 'dni' como clave foránea en lugar del ID convencional.
     *
     * @return BelongsTo
     */
    public function jugador()
    {
        return $this->belongsTo(Jugador::class, 'dni_jugador', 'dni');
    }

    /**
     * Relación con Equipo
     */
    public function equipo()
    {
        return $this->belongsTo(Equipo::class, 'idEquipo');
    }

    /**
     * Relación con Edición
     */
    public function edicion()
    {
        return $this->belongsTo(Edicion::class, 'idEdicion');
    }

    /**
     * Relación con Categoría
     */
    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'idCategoria');
    }

    /**
     * Scope para obtener solo jugadores activos
     */
    public function scopeActivos(Builder $query)
    {
        return $query->where('activo', true)->whereNull('fecha_baja');
    }

    /**
     * Scope para obtener jugadores de un equipo específico
     */
    public function scopeDeEquipo(Builder $query, $idEquipo, $idEdicion)
    {
        return $query->where('idEquipo', $idEquipo)
            ->where('idEdicion', $idEdicion);
    }

    /**
     * Scope para obtener jugadores por categoría
     */
    public function scopeDeCategoria(Builder $query, $idCategoria)
    {
        return $query->where('idCategoria', $idCategoria);
    }

    /**
     * Scope: Filtrar solo conflictos administrativos sin resolver
     *
     * Este scope es útil para mostrar la lista de conflictos pendientes
     * que requieren atención del administrador.
     *
     * Uso: ListaBuenaFe::conflictivosSinResolver()->get();
     *
     * @param  Builder  $query  Constructor de consulta Eloquent
     * @return Builder Consulta filtrada
     */
    public function scopeConflictivosSinResolver(Builder $query)
    {
        return $query->where('conflictivo', true)->where('resuelto', false);
    }

    /**
     * Dar de baja un jugador de la lista
     */
    public function darDeBaja($motivo = null)
    {
        $this->activo = false;
        $this->fecha_baja = now();
        $this->motivo_baja = $motivo;
        $this->save();
    }

    /**
     * Reactivar un jugador en la lista
     */
    public function reactivar()
    {
        $this->activo = true;
        $this->fecha_baja = null;
        $this->motivo_baja = null;
        $this->save();
    }

    /**
     * Resolver un conflicto administrativo
     *
     * Este método marca un conflicto como resuelto, registrando quién lo resolvió,
     * cuándo se resolvió, y opcionalmente notas explicativas sobre la resolución.
     *
     * Se utiliza tanto para resoluciones manuales (botón "Resolver" en UI) como
     * para resoluciones automáticas (cuando se da de baja un jugador conflictivo).
     *
     * @param  string|null  $usuario  Nombre o email del usuario que resuelve el conflicto
     * @param  string|null  $notas  Explicación detallada de cómo/por qué se resolvió
     * @return void
     *
     * @example
     * // Resolución manual
     * $listaBuenaFe->resolverConflicto('admin@example.com', 'Se confirmó que puede jugar');
     *
     * // Resolución automática al dar de baja
     * $listaBuenaFe->resolverConflicto('Sistema', 'Resuelto automáticamente al dar de baja');
     */
    public function resolverConflicto($usuario = null, $notas = null)
    {
        // Marcar como resuelto
        $this->resuelto = true;

        // Registrar timestamp actual de la resolución
        $this->fecha_resolucion = now();

        // Guardar quién resolvió (usuario o 'Sistema' si es automático)
        $this->resuelto_por = $usuario;

        // Almacenar notas explicativas (pueden ser null si no se proporcionan)
        $this->notas_resolucion = $notas;

        // Persistir cambios en la base de datos
        $this->save();
    }

    /**
     * Verificar si el jugador está suspendido por tarjeta roja
     *
     * Regla operativa vigente:
     * - La suspensión es de un solo partido.
     * - Solo se considera expulsión real cuando en la última planilla
     *   `tarjeta_roja = true` y `asistio = true`.
     * - Si la última planilla tiene roja marcada pero sin asistencia,
     *   se interpreta como "partido de suspensión" y no vuelve a bloquear
     *   automáticamente la siguiente planilla.
     */
    public function estaSuspendido()
    {
        // Obtener la última planilla del jugador en esta edición
        $ultimaPlanilla = PlanillaJugador::where('dni_jugador', $this->dni_jugador)
            ->where('idEdicion', $this->idEdicion)
            ->orderBy('id', 'desc')  // Usar ID en vez de updated_at (que no existe)
            ->first();

        if (! $ultimaPlanilla || ! $ultimaPlanilla->tarjeta_roja) {
            return false;
        }

        return (bool) $ultimaPlanilla->asistio;
    }
}
