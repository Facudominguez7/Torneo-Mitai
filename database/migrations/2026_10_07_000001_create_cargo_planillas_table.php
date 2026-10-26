<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuotas históricas de planilla. Sin FK hacia equipos, ediciones o padres:
     * los ids legacy y el snapshot conservan la historia aunque se borren.
     */
    public function up(): void
    {
        Schema::create('cargo_planillas', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo', 50);
            $table->string('parent_type');
            $table->unsignedBigInteger('parent_id');
            $table->unsignedBigInteger('idEquipo');
            $table->unsignedBigInteger('idEdicion');
            $table->unsignedBigInteger('idCategoria');
            $table->integer('precio_original');
            $table->json('snapshot');
            $table->timestamps();

            // Una única cuota por equipo y partido; el tipo financiero
            // distingue la fuente (partido vs instancia final).
            $table->unique(['tipo', 'parent_id', 'idEquipo']);
            $table->index(['parent_type', 'parent_id']);
            $table->index('idEquipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargo_planillas');
    }
};
