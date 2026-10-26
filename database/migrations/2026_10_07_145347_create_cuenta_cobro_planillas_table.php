<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuenta_cobro_planillas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('idEquipo');
            $table->unsignedBigInteger('idEdicion');
            $table->timestamps();
            $table->unique(['idEquipo', 'idEdicion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuenta_cobro_planillas');
    }
};
