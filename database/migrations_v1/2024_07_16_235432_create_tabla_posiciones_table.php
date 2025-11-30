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
        Schema::create('tabla_posiciones', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('idGrupo')->index('fkidgrupo');
            $table->integer('idEquipo')->index('fkidequipo');
            $table->integer('puntos');
            $table->integer('golesFavor');
            $table->integer('golesContra');
            $table->integer('diferenciaGoles');
            $table->integer('jugado');
            $table->integer('ganado');
            $table->integer('perdido');
            $table->integer('empatado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tabla_posiciones');
    }
};
