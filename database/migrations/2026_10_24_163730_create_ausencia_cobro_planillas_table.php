<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ausencia_cobro_planillas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cargo_id')->constrained('cargo_planillas')->restrictOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_nombre');
            $table->text('motivo')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('revertido_at')->nullable();
            $table->foreignId('revertido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revertido_por_nombre')->nullable();
            $table->index(['cargo_id', 'revertido_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ausencia_cobro_planillas');
    }
};
