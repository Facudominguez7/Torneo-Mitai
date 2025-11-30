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
        Schema::create('vallas_menos_vencidas', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('idEquipo')->index('idequipo');
            $table->integer('idCategoria')->index('fkidcategoria');
            $table->integer('idEdicion')->index('fkidediciones');
            $table->string('nombre', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vallas_menos_vencidas');
    }
};
