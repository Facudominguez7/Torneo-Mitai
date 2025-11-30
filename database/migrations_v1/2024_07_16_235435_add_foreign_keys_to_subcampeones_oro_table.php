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
        Schema::table('subcampeones_oro', function (Blueprint $table) {
            $table->foreign(['idCategoria'], 'relacion_subCampeon_oro_categoria')->references(['id'])->on('categorias')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idCopa'], 'relacion_subCampeon_oro_copa')->references(['id'])->on('copas')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEdicion'], 'relacion_subCampeon_oro_ediciones')->references(['id'])->on('ediciones')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEquipo'], 'relacion_subCampeon_oro_equipo')->references(['id'])->on('equipos')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subcampeones_oro', function (Blueprint $table) {
            $table->dropForeign('relacion_subCampeon_oro_categoria');
            $table->dropForeign('relacion_subCampeon_oro_copa');
            $table->dropForeign('relacion_subCampeon_oro_ediciones');
            $table->dropForeign('relacion_subCampeon_oro_equipo');
        });
    }
};
