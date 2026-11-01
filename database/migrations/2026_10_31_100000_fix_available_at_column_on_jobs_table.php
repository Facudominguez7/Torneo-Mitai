<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La migración original de jobs creó la columna como "availabe_at": la cola de base de datos
     * escribe available_at y no puede encolar nada. Solo corrige las bases que tienen el error.
     */
    public function up(): void
    {
        if (Schema::hasTable('jobs') && Schema::hasColumn('jobs', 'availabe_at') && ! Schema::hasColumn('jobs', 'available_at')) {
            Schema::table('jobs', function (Blueprint $table): void {
                $table->renameColumn('availabe_at', 'available_at');
            });
        }
    }

    /** No se vuelve a la columna mal escrita: dejaría la cola sin funcionar. */
    public function down(): void {}
};
