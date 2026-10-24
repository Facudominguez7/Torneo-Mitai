<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Corrige los nombres cargados antes de normalizar al guardar: "juan pablo" → "Juan Pablo".
     * Usa la misma regla que Jugador::capitalizarNombre y solo actualiza las filas que cambian.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('jugadores')->select(['dni', 'nombre', 'apellido'])->chunkById(500, function ($jugadores): void {
                foreach ($jugadores as $jugador) {
                    $nombre = Str::title(trim($jugador->nombre));
                    $apellido = Str::title(trim($jugador->apellido));
                    if ($nombre !== $jugador->nombre || $apellido !== $jugador->apellido) {
                        DB::table('jugadores')->where('dni', $jugador->dni)->update(['nombre' => $nombre, 'apellido' => $apellido]);
                    }
                }
            }, 'dni');
        });
    }

    /** Irreversible: la capitalización original no se guarda. */
    public function down(): void {}
};
