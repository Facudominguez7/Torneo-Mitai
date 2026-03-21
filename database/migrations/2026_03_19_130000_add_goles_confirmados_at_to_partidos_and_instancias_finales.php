<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partidos', function (Blueprint $table): void {
            $table->timestamp('goles_confirmados_at')->nullable()->after('jugado');
        });

        Schema::table('instancias_finales', function (Blueprint $table): void {
            $table->timestamp('goles_confirmados_at')->nullable()->after('cancha');
        });
    }

    public function down(): void
    {
        Schema::table('partidos', function (Blueprint $table): void {
            $table->dropColumn('goles_confirmados_at');
        });

        Schema::table('instancias_finales', function (Blueprint $table): void {
            $table->dropColumn('goles_confirmados_at');
        });
    }
};
