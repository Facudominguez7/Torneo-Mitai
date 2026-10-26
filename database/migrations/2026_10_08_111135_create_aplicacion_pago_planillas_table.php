<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aplicacion_pago_planillas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pago_id')->constrained('pago_planillas')->restrictOnDelete();
            $table->foreignId('cargo_id')->constrained('cargo_planillas')->restrictOnDelete();
            $table->integer('importe_ars');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aplicacion_pago_planillas');
    }
};
