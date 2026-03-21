<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tabla_goleadores', function (Blueprint $table): void {
            $table->unique(['dni_jugador', 'idEdicion', 'idCategoria'], 'unique_goleador_por_edicion_categoria');
        });
    }

    public function down(): void
    {
        Schema::table('tabla_goleadores', function (Blueprint $table): void {
            $table->dropUnique('unique_goleador_por_edicion_categoria');
        });
    }
};
