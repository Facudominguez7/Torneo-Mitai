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
        Schema::create('equipos_perdedores_semifinales_unicas', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('idEquipo')->index('fkidequipo');
            $table->integer('idCategoria')->index('fkidcategoria');
            $table->integer('idEdicion')->index('fkidediciones');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipos_perdedores_semifinales_unicas');
    }
};
