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
        Schema::table('equipos_ganadores_semifinales_oro', function (Blueprint $table) {
            $table->foreign(['idCategoria'], 'relacion_categoria_ganador_semifinal_oro')->references(['id'])->on('categorias')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEquipo'], 'relacion_equipos_ganadores_semifinales_oro')->references(['id'])->on('equipos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEdicion'], 'relacion_equipos_ganadores_semifinales_oro_ediciones')->references(['id'])->on('ediciones')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipos_ganadores_semifinales_oro', function (Blueprint $table) {
            $table->dropForeign('relacion_categoria_ganador_semifinal_oro');
            $table->dropForeign('relacion_equipos_ganadores_semifinales_oro');
            $table->dropForeign('relacion_equipos_ganadores_semifinales_oro_ediciones');
        });
    }
};
