<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Solo agregar timestamps si no existen (para evitar error de columna duplicada)
        if (!Schema::hasColumn('planilla_jugadores', 'created_at')) {
            Schema::table('planilla_jugadores', function (Blueprint $table) {
                // Agregar timestamps para poder ordenar correctamente
                $table->timestamps();
            });
            
            // Actualizar registros existentes con fecha actual
            DB::table('planilla_jugadores')->update([
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planilla_jugadores', function (Blueprint $table) {
            $table->dropTimestamps();
        });
    }
};
