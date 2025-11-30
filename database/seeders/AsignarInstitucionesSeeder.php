<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Equipo;
use App\Models\Institucion;
use App\Services\NormalizadorInstituciones;

class AsignarInstitucionesSeeder extends Seeder
{
    public function run(): void
    {
        // Map nombreBase => idInstitucion para reutilizar
        $cache = [];

        foreach (Equipo::all() as $equipo) {
            $nombreBase = NormalizadorInstituciones::estandarizar($equipo->nombre);

            if (!isset($cache[$nombreBase])) {
                $institucion = Institucion::firstOrCreate(['nombre' => $nombreBase]);
                $cache[$nombreBase] = $institucion->id;
            }

            if (!$equipo->idInstitucion) {
                $equipo->idInstitucion = $cache[$nombreBase];
                $equipo->save();
            }
        }
    }

    // Método legado eliminado: ahora se usa el servicio NormalizadorInstituciones
}
