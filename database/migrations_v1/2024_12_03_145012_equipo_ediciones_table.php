<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('equipo_ediciones', function (Blueprint $table) {
            $table->id();
            $table->Integer('idEdicion');
            $table->Integer('idEquipo');
            
            $table->foreign('idEdicion')->references('id')->on('ediciones')->onDelete('cascade');
            $table->foreign('idEquipo')->references('id')->on('equipos')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('equipo_ediciones');
    }
};
