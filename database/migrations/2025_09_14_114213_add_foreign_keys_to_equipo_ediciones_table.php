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
        Schema::table('equipo_ediciones', function (Blueprint $table) {
            $table->foreign(['idCategoria'], 'equipo_ediciones_idcategoria_foregin')->references(['id'])->on('categorias')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['idEdicion'])->references(['id'])->on('ediciones')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['idEquipo'])->references(['id'])->on('equipos')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipo_ediciones', function (Blueprint $table) {
            $table->dropForeign('equipo_ediciones_idcategoria_foregin');
            $table->dropForeign('equipo_ediciones_idedicion_foreign');
            $table->dropForeign('equipo_ediciones_idequipo_foreign');
        });
    }
};
