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
        Schema::create('equipos', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('idCategoria')->index('relacion_categorias');
            $table->integer('idEdicion')->index('fkidediciones');
            $table->string('nombre', 50);
            $table->string('foto')->nullable();
            $table->integer('victorias')->default(0);
            $table->integer('derrotas')->default(0);
            $table->integer('empates')->default(0);
            $table->integer('partidos_jugados')->default(0);
            $table->integer('golesFavor')->default(0);
            $table->integer('golesContra')->default(0);
            $table->integer('diferenciaGoles')->default(0);
            $table->integer('puntos')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipos');
    }
};
