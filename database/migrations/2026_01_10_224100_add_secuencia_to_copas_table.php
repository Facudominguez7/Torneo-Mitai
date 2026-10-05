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
        Schema::table('copas', function (Blueprint $table) {
            $table->unsignedInteger('secuencia')->default(50)->after('nombre');
        });

        // Actualizar las copas con su secuencia de orden
        // Menor número = mayor prioridad (aparece primero)
        $copas = [
            'Copa de Oro' => 1,
            'Copa de Plata' => 2,
            'Copa de Bronce' => 3,
            "Copa Mita'í" => 4,
        ];

        foreach ($copas as $nombre => $secuencia) {
            DB::table('copas')->where('nombre', $nombre)->update(['secuencia' => $secuencia]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('copas', function (Blueprint $table) {
            $table->dropColumn('secuencia');
        });
    }
};
