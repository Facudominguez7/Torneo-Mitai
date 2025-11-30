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
            $table->id(); // Clave primaria de la tabla
        
            // Relación con jugadores usando el DNI
            $table->integer('dni_jugador'); 
            $table->foreign('dni_jugador')
                  ->references('dni') // Referencia a la columna 'dni' en jugadores
                  ->on('jugadores')
                  ->onDelete('cascade');
        
            // Relación con equipos
            $table->integer('idEquipo');
            $table->foreign('idEquipo')
                  ->references('id')
                  ->on('equipos')
                  ->onDelete('cascade');
        
            // Relación con partidos
            $table->integer('partido_id');
            $table->foreign('partido_id')
                  ->references('id')
                  ->on('partidos')
                  ->onDelete('cascade');
        
            // Campos adicionales
            $table->integer('numero_camiseta'); // Número de camiseta
            $table->integer('goles')->default(0); // Goles anotados en el partido
            $table->boolean('asistio')->default(false); // Si el jugador asistió al partido
        
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
