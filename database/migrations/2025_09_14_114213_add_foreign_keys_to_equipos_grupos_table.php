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
        Schema::table('equipos_grupos', function (Blueprint $table) {
            $table->foreign(['idEquipo'], 'relacion_tabla_equipos')->references(['id'])->on('equipos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idGrupo'], 'relacion_tabla_grupos')->references(['id'])->on('grupos')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipos_grupos', function (Blueprint $table) {
            $table->dropForeign('relacion_tabla_equipos');
            $table->dropForeign('relacion_tabla_grupos');
        });
    }
};
