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
        Schema::create('lista_buena_fe', function (Blueprint $table) {
            $table->id();

            // Relación con jugador
            $table->integer('dni_jugador');
            $table->foreign('dni_jugador')
                ->references('dni')
                ->on('jugadores')
                ->onDelete('cascade');

            // Relación con equipo
            $table->integer('idEquipo');
            $table->foreign('idEquipo')
                ->references('id')
                ->on('equipos')
                ->onDelete('cascade');

            // Relación con edición
            $table->integer('idEdicion');
            $table->foreign('idEdicion')
                ->references('id')
                ->on('ediciones')
                ->onDelete('cascade');

            // Relación con categoría
            $table->integer('idCategoria');
            $table->foreign('idCategoria')
                ->references('id')
                ->on('categorias')
                ->onDelete('cascade');

            // Número de camiseta asignado
            $table->integer('numero_camiseta')->nullable();

            // Fecha de nacimiento del jugador (puede diferir de la tabla jugadores)
            $table->date('fecha_nacimiento');

            // Fecha de alta en la lista (cuándo fue agregado)
            $table->timestamp('fecha_alta')->useCurrent();

            // Fecha de baja (cuándo fue eliminado) - NULL si está activo
            $table->timestamp('fecha_baja')->nullable();

            // Motivo de baja (opcional)
            $table->string('motivo_baja')->nullable();

            // Estado: activo/inactivo
            $table->boolean('activo')->default(true);

            $table->timestamps();

            // Índices para búsquedas rápidas
            $table->index(['idEquipo', 'idEdicion', 'activo']);
            $table->index(['dni_jugador', 'idEdicion']);

            // Índice compuesto para evitar duplicados (se validará en la aplicación)
            $table->index(['dni_jugador', 'idEquipo', 'idEdicion']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lista_buena_fe');
    }
};
