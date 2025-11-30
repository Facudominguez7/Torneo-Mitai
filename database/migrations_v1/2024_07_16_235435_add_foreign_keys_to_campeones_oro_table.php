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
        Schema::table('campeones_oro', function (Blueprint $table) {
            $table->foreign(['idCategoria'], 'relacion_campeon_categoria')->references(['id'])->on('categorias')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idCopa'], 'relacion_campeon_copa')->references(['id'])->on('copas')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEquipo'], 'relacion_campeon_equipo')->references(['id'])->on('equipos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEdicion'], 'relacion_campeones_oro_edicion')->references(['id'])->on('ediciones')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campeones_oro', function (Blueprint $table) {
            $table->dropForeign('relacion_campeon_categoria');
            $table->dropForeign('relacion_campeon_copa');
            $table->dropForeign('relacion_campeon_equipo');
            $table->dropForeign('relacion_campeones_oro_edicion');
        });
    }
};
