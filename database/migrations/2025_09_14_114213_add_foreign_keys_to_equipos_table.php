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
        Schema::table('equipos', function (Blueprint $table) {
            $table->foreign(['idCategoria'], 'relacion_categorias')->references(['id'])->on('categorias')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEdicion'], 'relacion_equipos_ediciones')->references(['id'])->on('ediciones')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            $table->dropForeign('relacion_categorias');
            $table->dropForeign('relacion_equipos_ediciones');
        });
    }
};
