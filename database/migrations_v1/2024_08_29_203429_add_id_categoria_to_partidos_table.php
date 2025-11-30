<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIdCategoriaToPartidosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('partidos', function (Blueprint $table) {
            // Agregar columna 'idCategoria' como unsignedBigInteger que puede ser null
            $table->unsignedBigInteger('idCategoria')->nullable()->default(null)->after('idGrupo');

            // Agregar la relación con 'categorias' solo para nuevos registros
            $table->foreign('idCategoria')->references('id')->on('categorias')->onDelete('set null');
        });

        // Actualizar los registros existentes con un valor predeterminado
        DB::table('partidos')->update(['idCategoria' => null]); // Cambia `null` por el valor predeterminado que desees
    }

    public function down()
    {
        Schema::table('partidos', function (Blueprint $table) {
            // Eliminar la clave foránea
            $table->dropForeign(['idCategoria']);
            // Eliminar la columna 'idCategoria'
            $table->dropColumn('idCategoria');
        });
    }
};
