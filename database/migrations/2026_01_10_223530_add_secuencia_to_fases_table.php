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
        Schema::table('fases', function (Blueprint $table) {
            $table->unsignedInteger('secuencia')->default(50)->after('nombre');
        });

        // Actualizar las fases existentes con su secuencia de orden
        // Menor número = mayor prioridad (aparece primero)
        $fases = [
            'Final' => 1,
            'Final Ida' => 2,
            'Semifinal' => 10,
            'Semifinal Ida' => 11,
            'Semifinal Vuelta' => 12,
            'Semifinal Complementaria' => 13,
            'Semifinal Bronce' => 14,
            'Semifinal Oro y Plata' => 15,
            "Semifinal Copa Mita'í" => 16,
            'Cuartos de Final' => 20,
            'Tumbada' => 30,
            'Ronda Eliminatoria Oro y Plata' => 35,
        ];

        foreach ($fases as $nombre => $secuencia) {
            DB::table('fases')->where('nombre', $nombre)->update(['secuencia' => $secuencia]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fases', function (Blueprint $table) {
            $table->dropColumn('secuencia');
        });
    }
};

