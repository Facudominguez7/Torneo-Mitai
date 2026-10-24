<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Actualizar todos los usuarios para que no necesiten restablecer contraseña
        DB::table('users')
            ->where('restablecer_password', 'necesario')
            ->update(['restablecer_password' => 'innecesario']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No podemos saber quiénes lo tenían en 'necesario' antes,
        // así que el down no hace nada o podría revertir todos (peligroso).
        // Lo dejamos vacío.
    }
};
