<?php

use App\Models\CargoPlanilla;
use App\Models\EquipoGrupo;
use App\Models\Partido;
use App\Services\Cobros\ConfiguracionCobroService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require_once __DIR__.'/../Services/Cobros/FixturesCargos.php';

/** Datos descartables para navegador; nunca usar contra desarrollo. */
function crearFixtureCobrosPanel(bool $activar = true, int $cantidadCargos = 28): array
{
    if (DB::connection()->getDatabaseName() !== 'mitai_testing') {
        throw new RuntimeException('El fixture solo puede ejecutarse en mitai_testing.');
    }

    return DB::transaction(function () use ($activar, $cantidadCargos): array {
        $contexto = crearContextoCargos('Edición sintética UI cobros');
        $equipo = crearEquipoCargos($contexto, 'Equipo sintético Azul');
        $rival = crearEquipoCargos($contexto, 'Equipo sintético Rojo');
        foreach ([$equipo, $rival] as $participante) {
            EquipoGrupo::create(['idEquipo' => $participante->id, 'idGrupo' => $contexto['grupo']->id]);
        }
        $admin = crearAdminCargos('Administrador sintético cobros');
        $admin->forceFill([
            'email' => 'cobros-ui-'.$admin->id.'@example.test', 'email_verified_at' => now(),
            'restablecer_password' => 'innecesario',
            'password' => Hash::make('Cobros-Sintetico-2026!'),
        ])->save();
        app(ConfiguracionCobroService::class)->actualizarTarifa($admin, 25000);
        if ($activar) {
            app(ConfiguracionCobroService::class)->activar($admin);
        }
        $ids = [];
        for ($indice = 0; $indice < $cantidadCargos; $indice++) {
            $fecha = today()->subDays(28 - $indice)->setTime(10, 0)->format('Y-m-d H:i:s');
            $partido = Partido::create(crearDatosPartidoCargos($contexto, $equipo, $rival, ['horario_datetime' => $fecha]));
            $ids[] = CargoPlanilla::create([
                'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $partido->id,
                'idEquipo' => $equipo->id, 'idEdicion' => $contexto['edicion']->id,
                'idCategoria' => $contexto['categoria']->id, 'precio_original' => 25000,
                'snapshot' => ['equipo' => ['nombre' => $equipo->nombre], 'oponente' => ['nombre' => $rival->nombre]],
            ])->id;
        }

        return ['email' => $admin->email, 'idEdicion' => $contexto['edicion']->id, 'idEquipo' => $equipo->id, 'cargos' => $ids];
    });
}
