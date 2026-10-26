<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planilla_jugadores', function (Blueprint $table) {
            if (! Schema::hasColumn('planilla_jugadores', 'tarjeta_roja')) {
                $table->boolean('tarjeta_roja')->default(false)->after('asistio');
            }
        });
    }

    public function down(): void
    {
        Schema::table('planilla_jugadores', function (Blueprint $table) {
            if (Schema::hasColumn('planilla_jugadores', 'tarjeta_roja')) {
                $table->dropColumn('tarjeta_roja');
            }
        });
    }
};
