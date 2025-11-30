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
        Schema::table('lista_buena_fe', function (Blueprint $table) {
            $table->boolean('conflictivo')->default(false)->after('activo');
            $table->text('motivo_conflicto')->nullable()->after('conflictivo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lista_buena_fe', function (Blueprint $table) {
            $table->dropColumn(['conflictivo', 'motivo_conflicto']);
        });
    }
};
