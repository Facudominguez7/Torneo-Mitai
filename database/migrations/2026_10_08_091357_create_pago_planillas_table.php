<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago_planillas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuenta_cobro_planillas')->restrictOnDelete();
            $table->foreignId('cargo_origen_id')->constrained('cargo_planillas')->restrictOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_nombre');
            $table->integer('importe_ars');
            $table->string('medio', 20);
            $table->uuid('clave_idempotencia')->unique();
            $table->dateTime('recibido_at');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago_planillas');
    }
};
