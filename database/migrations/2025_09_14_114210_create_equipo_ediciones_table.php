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
        Schema::create('equipo_ediciones', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('idEdicion')->index('equipo_ediciones_idedicion_foreign');
            $table->integer('idEquipo')->index('equipo_ediciones_idequipo_foreign');
            $table->integer('idCategoria')->nullable()->index('equipo_ediciones_idcategoria_foreign');
            $table->integer('golesContra')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipo_ediciones');
    }
};
