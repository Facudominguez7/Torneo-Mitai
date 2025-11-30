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
        Schema::table('instancias_finales', function (Blueprint $table) {
            $table->foreign(['idCategoria'], 'instancias_fiinales_idcategoria_foreign')->references(['id'])->on('categorias')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['idCopa'], 'instancias_fiinales_idcopa_foreign')->references(['id'])->on('copas')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['idEdicion'], 'instancias_fiinales_idedicion_foreign')->references(['id'])->on('ediciones')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['idEquipoLocal'], 'instancias_fiinales_idequipolocal_foreign')->references(['id'])->on('equipos')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['idEquipoVisitante'], 'instancias_fiinales_idequipovisitante_foreign')->references(['id'])->on('equipos')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instancias_finales', function (Blueprint $table) {
            $table->dropForeign('instancias_fiinales_idcategoria_foreign');
            $table->dropForeign('instancias_fiinales_idcopa_foreign');
            $table->dropForeign('instancias_fiinales_idedicion_foreign');
            $table->dropForeign('instancias_fiinales_idequipolocal_foreign');
            $table->dropForeign('instancias_fiinales_idequipovisitante_foreign');
        });
    }
};
