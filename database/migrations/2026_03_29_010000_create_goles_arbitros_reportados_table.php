<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('goles_arbitros_reportados')) {
            Schema::create('goles_arbitros_reportados', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->integer('partido_id')->index();
                $table->string('partido_type', 100)->index();
                $table->integer('idEdicion')->index();
                $table->integer('idEquipo')->index();
                $table->unsignedBigInteger('idPlanillaJugador')->nullable();
                $table->integer('numero_camiseta')->nullable();
                $table->integer('goles_reportados')->default(0);
                $table->boolean('es_inconsistencia')->default(false);
                $table->string('motivo_inconsistencia')->nullable();
                $table->timestamps();

                $table->foreign('idPlanillaJugador')
                    ->references('id')
                    ->on('planilla_jugadores')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('goles_arbitros_reportados');
    }
};
