<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->unsignedSmallInteger('anio_desde')->nullable()->after('nombreCategoria');
            $table->unsignedSmallInteger('anio_hasta')->nullable()->after('anio_desde');
        });

        $this->completarRangosDesdeNombre();
    }

    public function down(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->dropColumn(['anio_desde', 'anio_hasta']);
        });
    }

    /**
     * Completa el rango de las categorías sin rango con los años que figuran
     * en su nombre (ej. "2020/2021" → 2020–2021; "Categoria 2015" → solo desde 2015).
     * Las que no tienen años, como "Femenino Sub 13", quedan sin rango.
     */
    public function completarRangosDesdeNombre(): void
    {
        DB::table('categorias')
            ->whereNull('anio_desde')
            ->whereNull('anio_hasta')
            ->orderBy('id')
            ->get(['id', 'nombreCategoria'])
            ->each(function (object $categoria): void {
                preg_match_all('/20\d{2}/', (string) $categoria->nombreCategoria, $coincidencias);
                $anios = array_map('intval', $coincidencias[0]);

                if ($anios === []) {
                    return;
                }

                DB::table('categorias')
                    ->where('id', $categoria->id)
                    ->update([
                        'anio_desde' => min($anios),
                        'anio_hasta' => max($anios) > min($anios) ? max($anios) : null,
                    ]);
            });
    }
};
