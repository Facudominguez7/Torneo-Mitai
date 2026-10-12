<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exencion_cobro_planillas', function (Blueprint $table): void {
            $table->unsignedBigInteger('politica_id')->nullable();
            $table->foreign('politica_id', 'exencion_cobro_politica_foreign')
                ->references('id')->on('politica_exencion_cobro_planillas')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exencion_cobro_planillas', function (Blueprint $table): void {
            $table->dropForeign('exencion_cobro_politica_foreign');
            $table->dropColumn('politica_id');
        });
    }
};
