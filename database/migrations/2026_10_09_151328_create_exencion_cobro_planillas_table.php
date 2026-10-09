<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exencion_cobro_planillas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cargo_id')->unique()->constrained('cargo_planillas')->restrictOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_nombre');
            $table->integer('importe_ars');
            $table->text('motivo')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exencion_cobro_planillas');
    }
};
