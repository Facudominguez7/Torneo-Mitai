<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('ediciones') && ! Schema::hasColumn('ediciones', 'jugadores_por_planilla')) {
            Schema::table('ediciones', function (Blueprint $table) {
                $table->unsignedSmallInteger('jugadores_por_planilla')->default(18);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('ediciones') && Schema::hasColumn('ediciones', 'jugadores_por_planilla')) {
            Schema::table('ediciones', function (Blueprint $table) {
                $table->dropColumn('jugadores_por_planilla');
            });
        }
    }
};
