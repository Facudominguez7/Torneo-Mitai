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
        Schema::table('planilla_jugadores', function (Blueprint $table) {
            $table->foreign(['idCategoria'], 'planilla_jugadores_categorias')->references(['id'])->on('categorias')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['idEquipo'], 'planilla_jugadores_equipos')->references(['id'])->on('equipos')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['dni_jugador'], 'planilla_jugadores_jugadores')->references(['dni'])->on('jugadores')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEdicion'], 'relacion_edicion_planillas')->references(['id'])->on('ediciones')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planilla_jugadores', function (Blueprint $table) {
            $table->dropForeign('planilla_jugadores_categorias');
            $table->dropForeign('planilla_jugadores_equipos');
            $table->dropForeign('planilla_jugadores_jugadores');
            $table->dropForeign('relacion_edicion_planillas');
        });
    }
};
