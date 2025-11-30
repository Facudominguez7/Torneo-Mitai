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
        Schema::create('partidos', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('idCategoria')->nullable()->index('fkidcategoria');
            $table->integer('idFechas')->index('fkidfechas');
            $table->integer('idEquipoLocal')->index('fkid_equipo_local');
            $table->integer('idEquipoVisitante')->index('fkid_equipo_visitante');
            $table->integer('idGrupo')->index('fkidgrupo');
            $table->integer('idEdicion')->index('fkidediciones');
            $table->integer('golesEquipoLocal')->nullable();
            $table->integer('golesEquipoVisitante')->nullable();
            $table->string('horario', 250)->nullable();
            $table->integer('cancha');
            $table->integer('jugado')->nullable();
            $table->dateTime('horario_datetime')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partidos');
    }
};
