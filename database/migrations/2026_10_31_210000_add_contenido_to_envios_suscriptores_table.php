<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los avisos del torneo son texto libre del administrador: el job necesita leerlo al enviar.
     * Los demás tipos arman su contenido en el momento y dejan la columna vacía.
     */
    public function up(): void
    {
        Schema::table('envios_suscriptores', function (Blueprint $table): void {
            $table->json('contenido')->nullable()->after('idCategoria');
        });
    }

    public function down(): void
    {
        Schema::table('envios_suscriptores', function (Blueprint $table): void {
            $table->dropColumn('contenido');
        });
    }
};
