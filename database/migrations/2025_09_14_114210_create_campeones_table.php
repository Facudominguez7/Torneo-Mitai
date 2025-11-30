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
        Schema::create('campeones', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('idEdicion')->nullable()->index('idedicion');
            $table->integer('idEquipo')->nullable()->index('idequipo');
            $table->integer('idCategoria')->nullable()->index('idcategoria');
            $table->integer('idCopa')->nullable()->index('idcopa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campeones');
    }
};
