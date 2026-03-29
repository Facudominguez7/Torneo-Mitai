<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trazabilidad_bajas_jugadores', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('lista_buena_fe_id')->nullable();
            $table->integer('dni_jugador')->index();
            $table->integer('idEquipo')->nullable()->index();
            $table->integer('idEdicion')->index();
            $table->integer('idCategoria')->nullable()->index();
            $table->string('nombre_jugador')->nullable();
            $table->string('apellido_jugador')->nullable();
            $table->string('equipo_nombre')->nullable();
            $table->string('categoria_nombre')->nullable();
            $table->string('edicion_nombre')->nullable();
            $table->timestamp('fecha_alta_lista')->nullable();
            $table->timestamp('fecha_baja');
            $table->string('motivo_baja')->nullable();
            $table->string('usuario_baja')->nullable();
            $table->unsignedInteger('total_goles')->default(0);
            $table->unsignedInteger('total_partidos')->default(0);
            $table->unsignedInteger('total_tarjetas_rojas')->default(0);
            $table->unsignedInteger('cantidad_planillas_archivadas')->default(0);
            $table->json('historial_planillas')->nullable();
            $table->timestamps();

            $table->foreign('lista_buena_fe_id')
                ->references('id')
                ->on('lista_buena_fe')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trazabilidad_bajas_jugadores');
    }
};
