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
        Schema::table('subcampeones', function (Blueprint $table) {
            $table->foreign(['idCategoria'], 'relacion_subcampeones_categoriaa')->references(['id'])->on('categorias')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idCopa'], 'relacion_subcampeones_copaa')->references(['id'])->on('copas')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEquipo'], 'relacion_subcampeones_equipoo')->references(['id'])->on('equipos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEdicion'], 'relacion_subcampeones_edicionn')->references(['id'])->on('ediciones')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subcampeones', function (Blueprint $table) {
            $table->dropForeign('relacion_subcampeones_categoriaa');
            $table->dropForeign('relacion_subcampeones_copaa');
            $table->dropForeign('relacion_subcampeones_equipoo');
            $table->dropForeign('relacion_subcampeones_edicionn');
        });
    }
};
