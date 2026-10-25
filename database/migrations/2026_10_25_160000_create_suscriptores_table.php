<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Personas suscriptas a los avisos por correo, con o sin cuenta. Activa = confirmada y sin baja.
     * Cada una elige qué avisos recibe y qué equipos sigue (los resultados solo llegan de esos equipos).
     */
    public function up(): void
    {
        Schema::create('suscriptores', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('recibe_resumen_fecha')->default(true);
            $table->boolean('recibe_avisos')->default(true);
            $table->boolean('recibe_proxima_fecha')->default(true);
            $table->timestamp('confirmado_at')->nullable();
            $table->timestamp('baja_at')->nullable();
            $table->string('resend_contacto_id')->nullable();
            $table->timestamps();
        });

        Schema::create('equipo_suscriptor', function (Blueprint $table): void {
            $table->foreignId('suscriptor_id')->constrained('suscriptores')->cascadeOnDelete();
            $table->integer('idEquipo');
            $table->foreign('idEquipo')->references('id')->on('equipos')->cascadeOnDelete();
            $table->primary(['suscriptor_id', 'idEquipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipo_suscriptor');
        Schema::dropIfExists('suscriptores');
    }
};
