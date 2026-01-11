<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Permite que idGrupo sea null para partidos interzonales
     */
    public function up(): void
    {
        // Primero eliminar la FK existente
        Schema::table('partidos', function (Blueprint $table) {
            $table->dropForeign('relacion_grupo');
        });

        // Hacer la columna nullable
        Schema::table('partidos', function (Blueprint $table) {
            $table->integer('idGrupo')->nullable()->change();
        });

        // Recrear la FK permitiendo null
        Schema::table('partidos', function (Blueprint $table) {
            $table->foreign(['idGrupo'], 'relacion_grupo')
                ->references(['id'])
                ->on('grupos')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir: hacer NOT NULL de nuevo
        Schema::table('partidos', function (Blueprint $table) {
            $table->dropForeign('relacion_grupo');
        });

        Schema::table('partidos', function (Blueprint $table) {
            $table->integer('idGrupo')->nullable(false)->change();
        });

        Schema::table('partidos', function (Blueprint $table) {
            $table->foreign(['idGrupo'], 'relacion_grupo')
                ->references(['id'])
                ->on('grupos')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }
};
