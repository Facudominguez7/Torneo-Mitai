<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('jugadores_partido', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idJugador');
            $table->unsignedBigInteger('idPartido'); // ID del partido (regular o instancia final)
            $table->string('partido_type'); // Indica si es un partido regular o una instancia final
            $table->integer('goles')->default(0);
            $table->boolean('asistio')->default(false);
            $table->timestamps();

            $table->foreign('idJugador')->references('id')->on('jugadores')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('jugadores_partido');
    }
};
