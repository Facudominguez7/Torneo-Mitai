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
        Schema::table('partidos', function (Blueprint $table) {
            $table->foreign(['idEquipoLocal'], 'realcion_equipo_local')->references(['id'])->on('equipos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idDia'], 'relacion_dia')->references(['id'])->on('dias')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEquipoVisitante'], 'relacion_equipo_visitante')->references(['id'])->on('equipos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idFechas'], 'relacion_fechas')->references(['id'])->on('fechas')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idGrupo'], 'relacion_grupo')->references(['id'])->on('grupos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEdicion'], 'relacion_partidos_ediciones')->references(['id'])->on('ediciones')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partidos', function (Blueprint $table) {
            $table->dropForeign('realcion_equipo_local');
            $table->dropForeign('relacion_dia');
            $table->dropForeign('relacion_equipo_visitante');
            $table->dropForeign('relacion_fechas');
            $table->dropForeign('relacion_grupo');
            $table->dropForeign('relacion_partidos_ediciones');
        });
    }
};
