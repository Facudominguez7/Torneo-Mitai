<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TransferenciaUsuariosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $usuarios = DB::table('usuarios')->get();

        foreach ($usuarios as $usuario) {
            $email = $usuario->email;
    
            // Verificar si el registro ya existe
            $existingUser = User::where('email', $email)->first();
    
            if (!$existingUser) {
                // Crear un nuevo registro si no existe
                $user = new User();
                $user->name = $usuario->nombre;
                $user->email = $email;
                $user->password = Hash::make($usuario->clave);
                $user->save();
            } else {
                // Actualizar el registro existente
                $existingUser->name = $usuario->nombre;
                $existingUser->password = Hash::make($usuario->clave);
                $existingUser->save();
            }
        }
    }
}
