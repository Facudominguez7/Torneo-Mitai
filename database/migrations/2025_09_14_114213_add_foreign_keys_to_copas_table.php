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
        Schema::table('copas', function (Blueprint $table) {
            $table->foreign(['idEdicion'], 'relacion_ediciones_copas')->references(['id'])->on('ediciones')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('copas', function (Blueprint $table) {
            $table->dropForeign('relacion_ediciones_copas');
        });
    }
};
