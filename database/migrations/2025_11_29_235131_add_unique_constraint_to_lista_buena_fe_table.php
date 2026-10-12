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
        // Identificar duplicados
        $duplicates = DB::table('lista_buena_fe')
            ->select('dni_jugador', 'idEquipo', 'idEdicion')
            ->groupBy('dni_jugador', 'idEquipo', 'idEdicion')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            // Obtener todos los registros para este duplicado
            $records = DB::table('lista_buena_fe')
                ->where('dni_jugador', $duplicate->dni_jugador)
                ->where('idEquipo', $duplicate->idEquipo)
                ->where('idEdicion', $duplicate->idEdicion)
                ->orderBy('activo', 'desc') // Activos primero
                ->orderBy('id', 'desc')     // Más recientes primero
                ->get();

            // El primero es el que guardamos (Activo y/o más reciente)
            if ($records->isNotEmpty()) {
                $keepId = $records->first()->id;

                // Eliminar el resto
                $idsToDelete = $records->pluck('id')->filter(function ($id) use ($keepId) {
                    return $id != $keepId;
                });

                if ($idsToDelete->isNotEmpty()) {
                    DB::table('lista_buena_fe')
                        ->whereIn('id', $idsToDelete)
                        ->delete();
                }
            }
        }

        Schema::table('lista_buena_fe', function (Blueprint $table) {
            $table->unique(['dni_jugador', 'idEquipo', 'idEdicion'], 'lista_buena_fe_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lista_buena_fe', function (Blueprint $table) {
            $table->dropUnique('lista_buena_fe_unique');
        });
    }
};
