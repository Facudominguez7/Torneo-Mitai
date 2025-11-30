<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Equipo;
use App\Models\EquipoEdicion;

class InicializarEquiposEdicionesSeeder extends Seeder
{
    public function run()
    {
        // Definir el id de la edición destino
        $idEdicionDestino = 1; // Cambia según la edición destino

        // Obtener todos los equipos de la tabla equipos que tienen el idEdicionDestino
        $equipos = Equipo::where('idEdicion', $idEdicionDestino)->get();

        foreach ($equipos as $equipo) {
            // Verificar si ya existe el equipo en la edición destino
            $existe = EquipoEdicion::where('idEquipo', $equipo->id)
                                    ->where('idEdicion', $idEdicionDestino)
                                    ->exists();

            // Si no existe, creamos el nuevo registro en la edición destino
            if (!$existe) {
                EquipoEdicion::create([
                    'idEquipo' => $equipo->id,
                    'idEdicion' => $idEdicionDestino,
                    'golesContra' => 0, 
                ]);
            }
        }
    }
}