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
        Schema::create('finales', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('idEquipoLocal')->index('fkidequipolocal');
            $table->integer('idEquipoVisitante')->index('fkidequipovisitante');
            $table->integer('idCopa')->index('fkidcopa');
            $table->integer('idCategoria')->index('fkidcategoria');
            $table->integer('idEdicion')->index('fkidediciones');
            $table->string('horario', 50);
            $table->integer('cancha');
            $table->integer('idDia')->index('fkiddia');
            $table->integer('golesEquipoLocal');
            $table->integer('golesEquipoVisitante');
            $table->integer('penalesEquipoLocal');
            $table->integer('penalesEquipoVisitante');
            $table->integer('jugado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finales');
    }
};
