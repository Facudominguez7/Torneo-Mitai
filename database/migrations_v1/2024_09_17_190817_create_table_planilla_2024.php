<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('planillas', function (Blueprint $table) {
            $table->integer('id', true);
            $table->Integer('idPartido');
            $table->Integer('idEquipo');
            $table->Integer('idJugador');
            $table->integer('numero_camiseta');
            $table->integer('goles')->default(0);
            $table->foreign('idPartido')->references('id')->on('partidos')->onDelete('cascade');
            $table->foreign('idEquipo')->references('id')->on('equipos')->onDelete('cascade');
            $table->foreign('idJugador')->references('id')->on('jugadores')->onDelete('cascade');
            $table->unique(['idEquipo', 'numero_camiseta']); // Unicidad del número de camiseta por equipo
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('planillas');
    }
};