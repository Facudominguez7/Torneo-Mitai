<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('jugadores_ediciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idJugador');
            $table->Integer('idEdicion');
            $table->Integer('idEquipo'); // El equipo del jugador en esta edición
            $table->timestamps();

            // Relaciones
            $table->foreign('idJugador')->references('id')->on('jugadores')->onDelete('cascade');
            $table->foreign('idEdicion')->references('id')->on('ediciones')->onDelete('cascade');
            $table->foreign('idEquipo')->references('id')->on('equipos')->onDelete('cascade');

            // Restricción única
            $table->unique(['idJugador', 'idEdicion'], 'jugador_edicion_unico');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('jugadores_ediciones');
    }
};
