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
            $table->foreign(['idEdicion'], 'subcampeones_ibfk_1')->references(['id'])->on('ediciones')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idEquipo'], 'subcampeones_ibfk_2')->references(['id'])->on('equipos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idCategoria'], 'subcampeones_ibfk_3')->references(['id'])->on('categorias')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['idCopa'], 'subcampeones_ibfk_4')->references(['id'])->on('copas')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subcampeones', function (Blueprint $table) {
            $table->dropForeign('subcampeones_ibfk_1');
            $table->dropForeign('subcampeones_ibfk_2');
            $table->dropForeign('subcampeones_ibfk_3');
            $table->dropForeign('subcampeones_ibfk_4');
        });
    }
};
