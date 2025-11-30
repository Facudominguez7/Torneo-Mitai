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
        Schema::table('vallas_menos_vencidas', function (Blueprint $table) {
            $table->foreign(['idCategoria'], 'relacion_categorias_valla_menos_vencida')->references(['id'])->on('categorias')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEquipo'], 'relacion_equipos_valla_menos_vencida')->references(['id'])->on('equipos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEdicion'], 'relacion_equipos_valla_menos_vencida_ediciones')->references(['id'])->on('ediciones')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vallas_menos_vencidas', function (Blueprint $table) {
            $table->dropForeign('relacion_categorias_valla_menos_vencida');
            $table->dropForeign('relacion_equipos_valla_menos_vencida');
            $table->dropForeign('relacion_equipos_valla_menos_vencida_ediciones');
        });
    }
};
