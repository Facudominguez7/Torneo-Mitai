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
        Schema::create('planilla_jugadores', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('dni_jugador')->index('planilla_jugadores_dni_jugador_foreign');
            $table->integer('idEquipo')->index('planilla_jugadores_equipos');
            $table->integer('idCategoria')->index('planilla_jugadores_categorias');
            $table->integer('partido_id');
            $table->string('partido_type', 100);
            $table->integer('idEdicion')->index('fkidedicion');
            $table->integer('numero_camiseta')->nullable()->default(0);
            $table->date('fecha_nacimiento')->nullable();
            $table->integer('goles')->default(0);
            $table->boolean('asistio');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planilla_jugadores');
    }
};
