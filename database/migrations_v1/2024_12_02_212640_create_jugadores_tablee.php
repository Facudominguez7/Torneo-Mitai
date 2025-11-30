<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('jugadores', function (Blueprint $table) {
            $table->id();
            $table->Integer('idEquipo');
            $table->Integer('idCategoria'); // Categoría del jugador
            $table->string('nombre');
            $table->integer('dni')->unique();
            $table->integer('numeroCamiseta');
            $table->timestamps();
        
            $table->foreign('idEquipo')->references('id')->on('equipos')->onDelete('cascade');
            $table->foreign('idCategoria')->references('id')->on('categorias')->onDelete('cascade');
            $table->unique(['idEquipo', 'dni']); // Evita duplicar jugadores en un mismo equipo
        });
    }

    public function down()
    {
        Schema::dropIfExists('jugadores');
    }
};
