<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reemplazos_equipos', function (Blueprint $table): void {
            $table->integer('id', true);
            $table->integer('idEdicion');
            $table->integer('idCategoria');
            $table->integer('idGrupo');
            $table->integer('idEquipoReemplazado');
            $table->integer('idEquipoReemplazante');
            $table->timestamps();

            $table->index(['idEdicion', 'idEquipoReemplazado'], 'reemplazos_equipos_edicion_reemplazado_index');

            $table->foreign('idEdicion')->references('id')->on('ediciones')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('idCategoria')->references('id')->on('categorias')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('idGrupo')->references('id')->on('grupos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('idEquipoReemplazado')->references('id')->on('equipos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('idEquipoReemplazante')->references('id')->on('equipos')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reemplazos_equipos');
    }
};
