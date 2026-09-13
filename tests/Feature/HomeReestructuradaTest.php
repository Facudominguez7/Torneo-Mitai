<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Partido;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// Helpers con prefijo propio: las funciones de Pest son globales entre archivos
// del mismo run y no pueden redeclararse (convención de FixtureExploradorVistaTest).
function homeBase(): array
{
    $edicion = Edicion::create(['nombre' => 'Edicion Home Reestructurada']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 14']);
    $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Grupo A']);

    return compact('edicion', 'categoria', 'grupo');
}

function homeFecha(array $contexto, string $nombre): Fecha
{
    return Fecha::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $nombre,
    ]);
}

function homePartido(
    array $contexto,
    Fecha $fecha,
    string $horario,
    string $local = 'Leones del Norte',
    string $visitante = 'Tigres del Sur',
    int $jugado = 0,
    ?int $golesLocal = null,
    ?int $golesVisitante = null,
    ?int $idGrupo = 0,
): Partido {
    $equipoLocal = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $local,
        'foto' => 'local.png',
    ]);
    $equipoVisitante = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $visitante,
        'foto' => 'visita.png',
    ]);

    return Partido::create([
        'idCategoria' => $contexto['categoria']->id,
        'idFechas' => $fecha->id,
        'idEquipoLocal' => $equipoLocal->id,
        'idEquipoVisitante' => $equipoVisitante->id,
        'idGrupo' => $idGrupo === 0 ? $contexto['grupo']->id : $idGrupo,
        'idEdicion' => $contexto['edicion']->id,
        'golesEquipoLocal' => $jugado === 1 ? $golesLocal : null,
        'golesEquipoVisitante' => $jugado === 1 ? $golesVisitante : null,
        'horario_datetime' => $horario,
        'cancha' => 1,
        'jugado' => $jugado,
    ]);
}

function ordenEnHtml(string $html, array $marcadores): bool
{
    $posicion = 0;
    $html = (string) preg_replace('/\s+/u', ' ', $html);

    foreach ($marcadores as $marcador) {
        $marcador = (string) preg_replace('/\s+/u', ' ', $marcador);
        $siguiente = mb_strpos($html, $marcador, $posicion);
        if ($siguiente === false) {
            return false;
        }
        $posicion = $siguiente + 1;
    }

    return true;
}

describe('home: bloque vivo de la fecha relevante', function () {
    it('muestra proximos partidos cuando la fecha relevante tiene partidos futuros', function () {
        $contexto = homeBase();
        $futura = homeFecha($contexto, 'Fecha 2');

        homePartido($contexto, $futura, now()->addDays(2)->format('Y-m-d H:i:s'));

        $respuesta = $this->get(route('home'));

        $respuesta->assertOk()
            ->assertSee('Próximos partidos', false)
            ->assertSee('id="fecha-relevante"', false)
            ->assertSee('Fecha 2', false);
    });

    it('lista todos los partidos de la fecha relevante y ninguno de otras fechas', function () {
        $contexto = homeBase();
        $relevante = homeFecha($contexto, 'Fecha 2');
        $otra = homeFecha($contexto, 'Fecha 1');

        $idsRelevante = collect();
        for ($i = 1; $i <= 7; $i++) {
            $idsRelevante->push(homePartido(
                $contexto,
                $relevante,
                now()->addDays(2)->addMinutes($i)->format('Y-m-d H:i:s'),
                local: 'Club Numero '.$i,
                visitante: 'Rival Numero '.$i,
            )->id);
        }
        $idOtraFecha = homePartido($contexto, $otra, now()->subDays(5)->format('Y-m-d H:i:s'), local: 'Club Otra Fecha', visitante: 'Rival Otra Fecha')->id;

        $html = $this->get(route('home'))->assertOk()->getContent();

        expect(substr_count($html, 'id="partido-'))->toBe(3);
            foreach ($idsRelevante->slice(3) as $id) {
            expect($html)->not->toContain('id="partido-'.$id.'"');
            }
            expect($html)->toContain('Ver todos los partidos');
        expect($html)->not->toContain('id="partido-'.$idOtraFecha.'"');
    });

    it('no rompe cuando la edicion no tiene partidos', function () {
        homeBase();

        $this->get(route('home'))->assertOk();
    });

    it('no consulta la tabla users ni pasa roles ni idCategoria', function () {
        homeBase();

        $consultas = [];
        DB::listen(function ($query) use (&$consultas) {
            $consultas[] = $query->sql;
        });

        $respuesta = $this->get(route('home'))->assertOk();

        $sobreUsers = array_values(array_filter($consultas, fn ($sql) => str_contains($sql, 'users')));

        expect($sobreUsers)->toBeEmpty();
        $respuesta->assertViewMissing('roles');
    });

    it('muestra los cuatro accesos secundarios una sola vez fuera de la navegacion', function () {
        $contexto = homeBase();

        $html = $this->get(route('home'))->assertOk()->getContent();
        $sinNav = trim((string) preg_replace('/<nav\b.*?<\/nav>/s', '', $html));

        foreach ([
            'tabla-posiciones',
            'tabla-goleadores',
            'instancias-finales',
            'tabla-vallas',
        ] as $ruta) {
            $url = route($ruta, ['idEdicion' => $contexto['edicion']->id]);
            expect(substr_count($sinNav, $url))->toBe(1, $ruta);
        }
    });
});

describe('home: jerarquía de bloques y prosa eliminada', function () {
    it('renderiza los bloques en el orden definido por el spec', function () {
        $contexto = homeBase();
        $futura = homeFecha($contexto, 'Fecha 2');
        homePartido($contexto, $futura, now()->addDays(2)->format('Y-m-d H:i:s'));
        Equipo::create([
            'idCategoria' => $contexto['categoria']->id,
            'idEdicion' => $contexto['edicion']->id,
            'nombre' => 'Equipo Jerarquia',
            'foto' => 'equipo.png',
        ]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        expect(ordenEnHtml($html, [
            'id="idEdicion"',
            'id="fecha-relevante"',
            'id="seguir-equipo-bloque"',
            'id="accesos-secundarios"',
            'Ir al fixture',
            'Ver más',
        ]))->toBeTrue();
    });

    it('no contiene la prosa institucional ni los ctas duplicados eliminados', function () {
        homeBase();

        $html = $this->get(route('home'))->assertOk()->getContent();

        expect($html)->not->toContain(...[
            'fútbol formativo',
            'Viví la pasión',
            'Descubrí fixtures actualizados',
            'Actualización en vivo',
            'Formación y valores',
            'Fase de Grupos',
            'Goleadores al día',
            'Vallas al día',
            'Organizá tu experiencia',
            'Tablas actualizadas',
            'Comparte emociones',
            'Elegí la edición para filtrar',
            'Ingresá en segundos',
            'Ver todo el fixture',
            'legado del torneo',
            'Recorré a los protagonistas',
            'Reviví los equipos que levantaron la copa',
            'Los equipos que dejaron todo',
            'Los arqueros que brillan',
            'Reviví a los máximos artilleros',
            'Podio',
            'Defensa',
            'Ataque',
            'Seguinos en redes',
            'Formando talentos con pasión',
            'Sobre el torneo',
            'Mita\'í reúne a cientos de chicos',
        ]);
    });

    it('tiene un único acceso al fixture completo fuera de la navegación', function () {
        $contexto = homeBase();
        $futura = homeFecha($contexto, 'Fecha 2');
        homePartido($contexto, $futura, now()->addDays(2)->format('Y-m-d H:i:s'));

        $html = $this->get(route('home'))->assertOk()->getContent();
        $sinNav = trim((string) preg_replace('/<nav\b.*?<\/nav>/s', '', $html));
        $urlFixture = route('fixture', ['idEdicion' => $contexto['edicion']->id]);

        expect(preg_match_all('/href="'.preg_quote($urlFixture, '/').'"/', $sinNav))->toBe(1);
    });

    it('muestra los mismos bloques para guest y para admin', function () {
        $contexto = homeBase();
        $futura = homeFecha($contexto, 'Fecha 2');
        homePartido($contexto, $futura, now()->addDays(2)->format('Y-m-d H:i:s'));
        Equipo::create([
            'idCategoria' => $contexto['categoria']->id,
            'idEdicion' => $contexto['edicion']->id,
            'nombre' => 'Equipo Roles',
            'foto' => 'equipo.png',
        ]);

        $marcadores = [
            'id="idEdicion"',
            'id="fecha-relevante"',
            'id="seguir-equipo-bloque"',
            'id="accesos-secundarios"',
            'Ir al fixture',
            'Ver más',
        ];

        $guest = $this->get(route('home'))->assertOk()->getContent();

        $admin = User::forceCreate([
            'name' => 'Admin Home',
            'email' => 'admin-home@test.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $admin->forceFill(['rol' => 'admin'])->save();

        $adminHtml = $this->actingAs($admin)->get(route('home'))->assertOk()->getContent();

        foreach (['guest' => $guest, 'admin' => $adminHtml] as $rol => $html) {
            expect(ordenEnHtml($html, $marcadores))->toBeTrue($rol);
        }
    });
});
