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
        Schema::table('tabla_posiciones', function (Blueprint $table) {
            $table->foreign(['idEquipo'], 'relacion_equipos')->references(['id'])->on('equipos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idGrupo'], 'relacion_gruposs')->references(['id'])->on('grupos')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tabla_posiciones', function (Blueprint $table) {
            $table->dropForeign('relacion_equipos');
            $table->dropForeign('relacion_gruposs');
        });
    }
};
