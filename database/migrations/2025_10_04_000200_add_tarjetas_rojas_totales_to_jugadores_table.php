<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('jugadores', 'tarjetas_rojas_totales')) {
            Schema::table('jugadores', function (Blueprint $table) {
                $table->unsignedInteger('tarjetas_rojas_totales')->default(0)->after('goles_totales');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('jugadores', 'tarjetas_rojas_totales')) {
            Schema::table('jugadores', function (Blueprint $table) {
                $table->dropColumn('tarjetas_rojas_totales');
            });
        }
    }
};
