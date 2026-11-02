<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partidos', function (Blueprint $table): void {
            $table->boolean('resultado_no_vino_suprimido')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('partidos', function (Blueprint $table): void {
            $table->dropColumn('resultado_no_vino_suprimido');
        });
    }
};
