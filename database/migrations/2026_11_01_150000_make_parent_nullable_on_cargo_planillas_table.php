<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deudas manuales sin partido: el cargo de tipo deuda_manual no tiene
     * cobro padre. Los cargos existentes no cambian.
     */
    public function up(): void
    {
        Schema::table('cargo_planillas', function (Blueprint $table): void {
            $table->string('parent_type')->nullable()->change();
            $table->unsignedBigInteger('parent_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('cargo_planillas')->whereNull('parent_type')->orWhereNull('parent_id')->exists()) {
            throw new RuntimeException('Hay deudas manuales sin partido: no se puede volver a exigir el partido en cargo_planillas.');
        }

        Schema::table('cargo_planillas', function (Blueprint $table): void {
            $table->string('parent_type')->nullable(false)->change();
            $table->unsignedBigInteger('parent_id')->nullable(false)->change();
        });
    }
};
