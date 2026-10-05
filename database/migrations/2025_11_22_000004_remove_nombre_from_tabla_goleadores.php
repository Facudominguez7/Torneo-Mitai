<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Eliminar columna 'nombre' de tabla_goleadores ya que ahora se obtiene
     * dinámicamente desde la relación con Jugador
     */
    public function up(): void
    {
        Schema::table('tabla_goleadores', function (Blueprint $table) {
            // Eliminar columna nombre duplicada
            $table->dropColumn('nombre');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tabla_goleadores', function (Blueprint $table) {
            // Restaurar columna si se revierte
            $table->string('nombre', 255)->nullable();
        });
    }
};
