<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para agregar la columna resultadoGlobal a la tabla instancias_finales.
 * Esta columna almacena el resultado global del partido en instancias finales.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('instancias_finales', 'resultadoGlobal')) {
            Schema::table('instancias_finales', function (Blueprint $table) {
                $table->string('resultadoGlobal', 10)->nullable()->after('penalesEquipoVisitante');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('instancias_finales', 'resultadoGlobal')) {
            Schema::table('instancias_finales', function (Blueprint $table) {
                $table->dropColumn('resultadoGlobal');
            });
        }
    }
};
