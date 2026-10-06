<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_cobro_planillas', function (Blueprint $table): void {
            $table->id();
            $table->integer('tarifa_ars')->default(25000);
            $table->timestamp('activado_at')->nullable();
            $table->timestamps();
        });

        // Singleton global (fila id 1) sembrado con QueryBuilder, sin Eloquent.
        DB::table('configuracion_cobro_planillas')->insert([
            'id' => 1,
            'tarifa_ars' => 25000,
            'activado_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_cobro_planillas');
    }
};
