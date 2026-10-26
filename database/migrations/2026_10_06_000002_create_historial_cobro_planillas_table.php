<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_cobro_planillas', function (Blueprint $table): void {
            $table->id();
            $table->string('accion', 50);
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_nombre');
            $table->json('valores_antes');
            $table->json('valores_despues');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_cobro_planillas');
    }
};
