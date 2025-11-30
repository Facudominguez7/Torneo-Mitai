<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Agregar constraint único para evitar jugadores duplicados en la misma planilla
     */
    public function up(): void
    {
        Schema::table('planilla_jugadores', function (Blueprint $table) {
            // Constraint único: un jugador no puede estar duplicado en la misma planilla
            $table->unique(['dni_jugador', 'idEquipo', 'partido_id', 'partido_type'], 'unique_jugador_partido');
            
            // Index para búsquedas de número de camiseta por equipo y partido
            $table->index(['idEquipo', 'partido_id', 'numero_camiseta'], 'idx_equipo_partido_camiseta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planilla_jugadores', function (Blueprint $table) {
            $table->dropUnique('unique_jugador_partido');
            $table->dropIndex('idx_equipo_partido_camiseta');
        });
    }
};
