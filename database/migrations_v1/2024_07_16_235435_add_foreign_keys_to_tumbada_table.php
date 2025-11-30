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
        Schema::table('tumbada', function (Blueprint $table) {
            $table->foreign(['idCategoria'], 'relacion_tumbada_categoria')->references(['id'])->on('categorias')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idDia'], 'relacion_tumbada_dias')->references(['id'])->on('dias')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEdicion'], 'relacion_tumbada_edicion')->references(['id'])->on('ediciones')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEquipoLocal'], 'relacion_tumbada_equipoLocal')->references(['id'])->on('equipos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEquipoVisitante'], 'relacion_tumbada_equipoVisitante')->references(['id'])->on('equipos')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tumbada', function (Blueprint $table) {
            $table->dropForeign('relacion_tumbada_categoria');
            $table->dropForeign('relacion_tumbada_dias');
            $table->dropForeign('relacion_tumbada_edicion');
            $table->dropForeign('relacion_tumbada_equipoLocal');
            $table->dropForeign('relacion_tumbada_equipoVisitante');
        });
    }
};
