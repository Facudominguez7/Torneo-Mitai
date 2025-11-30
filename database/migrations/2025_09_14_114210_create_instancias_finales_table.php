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
        Schema::create('instancias_finales', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('idEquipoLocal')->index('instancias_fiinales_idequipolocal_foreign');
            $table->integer('idEquipoVisitante')->index('instancias_fiinales_idequipovisitante_foreign');
            $table->integer('idCopa')->nullable()->index('instancias_fiinales_idcopa_foreign');
            $table->integer('idCategoria')->index('instancias_fiinales_idcategoria_foreign');
            $table->integer('idEdicion')->index('instancias_fiinales_idedicion_foreign');
            $table->integer('idFase')->index('fkidfase');
            $table->dateTime('horario');
            $table->string('cancha');
            $table->integer('golesEquipoLocal')->nullable();
            $table->integer('golesEquipoVisitante')->nullable();
            $table->integer('penalesEquipoLocal')->nullable();
            $table->integer('penalesEquipoVisitante')->nullable();
            $table->string('resultadoGlobal', 10)->nullable();
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
