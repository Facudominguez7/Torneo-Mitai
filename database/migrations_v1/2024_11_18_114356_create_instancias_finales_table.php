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
        // Tabla para instancias finales (partidos de cada fase)
        Schema::create('instancias_finales', function (Blueprint $table) {
            $table->id();
            $table->integer('idEquipoLocal');
            $table->integer('idEquipoVisitante');
            $table->integer('idCopa');
            $table->integer('idCategoria');
            $table->integer('idEdicion');
            $table->integer('idFase'); // Relación con la tabla 'fases'
            $table->dateTime('horario');
            $table->string('cancha');
            $table->integer('golesEquipoLocal')->default(0);
            $table->integer('golesEquipoVisitante')->default(0);
            $table->integer('penalesEquipoLocal')->default(0);
            $table->integer('penalesEquipoVisitante')->default(0);
            $table->boolean('jugado')->default(false);

            // Relacionar con otras tablas
            $table->foreign('idEquipoLocal')->references('id')->on('equipos')->onDelete('cascade');
            $table->foreign('idEquipoVisitante')->references('id')->on('equipos')->onDelete('cascade');
            $table->foreign('idCopa')->references('id')->on('copas')->onDelete('cascade');
            $table->foreign('idCategoria')->references('id')->on('categorias')->onDelete('cascade');
            $table->foreign('idEdicion')->references('id')->on('ediciones')->onDelete('cascade');
            $table->foreign('idFase')->references('id')->on('fases')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instancias_finales');
    }
};
