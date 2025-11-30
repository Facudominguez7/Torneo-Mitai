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
        Schema::create('tabla_goleadores', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('idCategoria')->index('fkidcategoria');
            $table->integer('idEdicion')->index('fkidedicion');
            $table->integer('idEquipo')->index('fkidequipo');
            $table->string('nombre', 100);
            $table->integer('cantidadGoles');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tabla_goleadores');
    }
};
