<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tabla_goleadores', function (Blueprint $table) {
            $table->foreign(['idCategoria'], 'relacion_categorias_tabla_goleadores')->references(['id'])->on('categorias')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEdicion'], 'relacion_ediciones_tabla_goleadores')->references(['id'])->on('ediciones')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEquipo'], 'relacion_equipo_tabla_goleadores')->references(['id'])->on('equipos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['dni_jugador'], 'relacion_jugador_tabla_goleadores')->references(['dni'])->on('jugadores')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tabla_goleadores', function (Blueprint $table) {
            $table->dropForeign('relacion_categorias_tabla_goleadores');
            $table->dropForeign('relacion_ediciones_tabla_goleadores');
            $table->dropForeign('relacion_equipo_tabla_goleadores');
            $table->dropForeign('relacion_jugador_tabla_goleadores');
        });
    }
};
