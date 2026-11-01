<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Equipo que ganó 3 a 0 porque el rival no vino. Distingue el resultado
     * automático de uno cargado a mano; los partidos existentes quedan en null.
     */
    public function up(): void
    {
        Schema::table('partidos', function (Blueprint $table): void {
            $table->integer('ganador_por_no_vino')->nullable()->after('jugado');
        });
    }

    public function down(): void
    {
        Schema::table('partidos', function (Blueprint $table): void {
            $table->dropColumn('ganador_por_no_vino');
        });
    }
};
