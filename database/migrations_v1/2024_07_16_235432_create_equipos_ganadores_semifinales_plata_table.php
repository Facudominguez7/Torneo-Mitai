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
        Schema::create('equipos_ganadores_semifinales_plata', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('idEquipo')->index('fkidequipo');
            $table->integer('idCategoria')->index('fkidcategoria');
            $table->integer('idEdicion')->index('fkidedicion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipos_ganadores_semifinales_plata');
    }
};
