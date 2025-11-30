<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Agregar campos de resolución de conflictos a lista_buena_fe
 * 
 * Esta migración agrega campos necesarios para rastrear la resolución de conflictos
 * administrativos en la Lista de Buena Fe. Permite registrar quién resolvió el conflicto,
 * cuándo se resolvió, y notas explicativas sobre la resolución.
 * 
 * @author Sistema de Gestión Torneo Mitai
 * @date 2025-11-29
 */
return new class extends Migration
{
    /**
     * Ejecutar la migración
     * 
     * Agrega cuatro nuevos campos a la tabla lista_buena_fe:
     * - resuelto: Boolean que indica si el conflicto administrativo fue resuelto
     * - fecha_resolucion: Timestamp que registra cuándo se resolvió el conflicto
     * - resuelto_por: String que guarda el nombre/email del usuario que resolvió
     * - notas_resolucion: Text para almacenar explicaciones detalladas de la resolución
     */
    public function up(): void
    {
        Schema::table('lista_buena_fe', function (Blueprint $table) {
            // Campo boolean para marcar si el conflicto fue resuelto (default: false = pendiente)
            $table->boolean('resuelto')->default(false)->after('motivo_conflicto');
            
            // Timestamp nullable para registrar el momento exacto de la resolución
            $table->timestamp('fecha_resolucion')->nullable()->after('resuelto');
            
            // String nullable para identificar qué usuario/administrador resolvió el conflicto
            $table->string('resuelto_por')->nullable()->after('fecha_resolucion');
            
            // Campo de texto para notas explicativas sobre cómo/por qué se resolvió
            $table->text('notas_resolucion')->nullable()->after('resuelto_por');
        });
    }

    /**
     * Revertir la migración
     * 
     * Elimina los cuatro campos agregados en el método up().
     * Esto permite hacer rollback si es necesario sin perder la estructura original.
     */
    public function down(): void
    {
        Schema::table('lista_buena_fe', function (Blueprint $table) {
            // Eliminar todos los campos de resolución en orden inverso
            $table->dropColumn(['resuelto', 'fecha_resolucion', 'resuelto_por', 'notas_resolucion']);
        });
    }
};
