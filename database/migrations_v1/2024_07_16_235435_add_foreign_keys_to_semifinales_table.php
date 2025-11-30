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
        Schema::table('semifinales', function (Blueprint $table) {
            $table->foreign(['idCopa'], 'relacion_copas')->references(['id'])->on('copas')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idDia'], 'relacion_dias')->references(['id'])->on('dias')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEquipoVisitante'], 'relacion_equipo-visitante')->references(['id'])->on('equipos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEquipoLocal'], 'relacion_equipo_local')->references(['id'])->on('equipos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEdicion'], 'relacion_semifinales_ediciones')->references(['id'])->on('ediciones')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idCategoria'], 'relacionCategorias')->references(['id'])->on('categorias')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('semifinales', function (Blueprint $table) {
            $table->dropForeign('relacion_copas');
            $table->dropForeign('relacion_dias');
            $table->dropForeign('relacion_equipo-visitante');
            $table->dropForeign('relacion_equipo_local');
            $table->dropForeign('relacion_semifinales_ediciones');
            $table->dropForeign('relacionCategorias');
        });
    }
};
