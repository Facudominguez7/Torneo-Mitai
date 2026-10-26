<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Limpiar duplicados antes de aplicar la restricción
        // Mantenemos el registro más reciente (mayor ID)
        $duplicates = DB::table('planilla_jugadores')
            ->select('dni_jugador', 'partido_id', 'partido_type', DB::raw('MAX(id) as max_id'))
            ->groupBy('dni_jugador', 'partido_id', 'partido_type')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('planilla_jugadores')
                ->where('dni_jugador', $duplicate->dni_jugador)
                ->where('partido_id', $duplicate->partido_id)
                ->where('partido_type', $duplicate->partido_type)
                ->where('id', '<', $duplicate->max_id)
                ->delete();
        }

        Schema::table('planilla_jugadores', function (Blueprint $table) {
            $table->unique(['dni_jugador', 'partido_id', 'partido_type'], 'planilla_jugadores_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planilla_jugadores', function (Blueprint $table) {
            $table->dropUnique('planilla_jugadores_unique');
        });
    }
};
