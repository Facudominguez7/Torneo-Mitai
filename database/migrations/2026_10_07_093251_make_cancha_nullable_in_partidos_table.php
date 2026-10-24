<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partidos', function (Blueprint $table) {
            $table->integer('cancha')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('partidos')->whereNull('cancha')->exists()) {
            throw new RuntimeException('No se puede exigir cancha mientras haya partidos sin cancha definida.');
        }

        Schema::table('partidos', function (Blueprint $table) {
            $table->integer('cancha')->nullable(false)->change();
        });
    }
};
