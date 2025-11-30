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
        Schema::create('campeones_plata', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('idEquipo')->index('fkidequipo');
            $table->integer('idCategoria')->index('fkidcategoria');
            $table->integer('idCopa')->index('fkidcopa');
            $table->integer('idEdicion')->index('fkidedicion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campeones_plata');
    }
};
