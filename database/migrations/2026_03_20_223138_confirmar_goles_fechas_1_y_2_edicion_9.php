<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Marca como confirmados los goles de partidos de fechas 1 y 2 de la edición 9.
     */
    public function up(): void
    {
        $fechaIds = DB::table('fechas')
            ->whereIn('nombre', ['1° Fecha', '2° Fecha'])
            ->pluck('id');

        DB::table('partidos')
            ->where('idEdicion', 9)
            ->whereIn('idFechas', $fechaIds)
            ->whereNull('goles_confirmados_at')
            ->update(['goles_confirmados_at' => now()]);
    }

    /**
     * Revierte la confirmación de goles.
     */
    public function down(): void
    {
        $fechaIds = DB::table('fechas')
            ->whereIn('nombre', ['1° Fecha', '2° Fecha'])
            ->pluck('id');

        DB::table('partidos')
            ->where('idEdicion', 9)
            ->whereIn('idFechas', $fechaIds)
            ->update(['goles_confirmados_at' => null]);
    }
};
