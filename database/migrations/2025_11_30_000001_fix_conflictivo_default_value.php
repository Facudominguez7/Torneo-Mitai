<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Primero, actualizar todos los registros existentes que tengan NULL
        DB::table('lista_buena_fe')
            ->whereNull('conflictivo')
            ->update(['conflictivo' => false]);

        // Luego, modificar la columna para asegurar que tenga default
        Schema::table('lista_buena_fe', function (Blueprint $table) {
            $table->boolean('conflictivo')->default(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No es necesario revertir, ya que solo estamos asegurando el default
    }
};
