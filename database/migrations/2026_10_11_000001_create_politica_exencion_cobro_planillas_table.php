<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sin FK a equipo/edición, como cargo_planillas: conservamos los ids
     * históricos sin imponer nuevas restricciones al borrado legacy.
     */
    public function up(): void
    {
        Schema::create('politica_exencion_cobro_planillas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('idEquipo');
            $table->unsignedBigInteger('idEdicion');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_nombre');
            $table->text('motivo')->nullable();
            $table->timestamp('created_at');
            $table->unique(['idEquipo', 'idEdicion'], 'politica_exencion_equipo_edicion_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('politica_exencion_cobro_planillas');
    }
};
