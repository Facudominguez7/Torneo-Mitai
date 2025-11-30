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
        Schema::table('finales', function (Blueprint $table) {
            $table->foreign(['idCategoria'], 'relacion_categoria_final')->references(['id'])->on('categorias')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idCopa'], 'relacion_copa_final')->references(['id'])->on('copas')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idDia'], 'relacion_dia_final')->references(['id'])->on('dias')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEdicion'], 'relacion_ediciones_final')->references(['id'])->on('ediciones')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEquipoLocal'], 'relacion_equipo_local_final')->references(['id'])->on('equipos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEquipoVisitante'], 'relacion_equipo_visitante_final')->references(['id'])->on('equipos')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finales', function (Blueprint $table) {
            $table->dropForeign('relacion_categoria_final');
            $table->dropForeign('relacion_copa_final');
            $table->dropForeign('relacion_dia_final');
            $table->dropForeign('relacion_ediciones_final');
            $table->dropForeign('relacion_equipo_local_final');
            $table->dropForeign('relacion_equipo_visitante_final');
        });
    }
};
