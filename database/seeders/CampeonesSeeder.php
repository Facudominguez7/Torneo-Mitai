<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class CampeonesSeeder extends Seeder
{
    public function run()
    {
        // Transferir datos desde campeones_oro
        $oroData = DB::table('campeones_oro')->get();

        foreach ($oroData as $data) {
            DB::table('campeones')->insert([
                'idEdicion' => $data->idEdicion,
                'idEquipo' => $data->idEquipo,
                'idCategoria' => $data->idCategoria,
                'idCopa' => $data->idCopa,
            ]);
        }

        // Transferir datos desde campeones_plata
        $plataData = DB::table('campeones_plata')->get();

        foreach ($plataData as $data) {
            DB::table('campeones')->insert([
                'idEdicion' => $data->idEdicion,
                'idEquipo' => $data->idEquipo,
                'idCategoria' => $data->idCategoria,
                'idCopa' => $data->idCopa,
            ]);
        }
    }
}
