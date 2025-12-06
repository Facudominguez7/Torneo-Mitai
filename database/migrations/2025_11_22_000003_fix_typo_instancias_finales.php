<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Corregir typo en nombres de columnas de instancias_finales
     */
    public function up(): void
    {
        Schema::table('instancias_finales', function (Blueprint $table) {
            // Renombrar columnas con typo (solo si existen con el nombre con typo)
            if (Schema::hasColumn('instancias_finales', 'resoltadoGlobalLocal')) {
                $table->renameColumn('resoltadoGlobalLocal', 'resultadoGlobalLocal');
            }
            if (Schema::hasColumn('instancias_finales', 'resoltadoGlobalVisitante')) {
                $table->renameColumn('resoltadoGlobalVisitante', 'resultadoGlobalVisitante');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instancias_finales', function (Blueprint $table) {
            // Revertir cambios
            $table->renameColumn('resultadoGlobalLocal', 'resoltadoGlobalLocal');
            $table->renameColumn('resultadoGlobalVisitante', 'resoltadoGlobalVisitante');
        });
    }
};
