<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Partido;
use App\Models\User;
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

describe('home: buscador protagonista y tema dual (artboard 06)', function () {
    it('renderiza los bloques en el orden definido por el artboard', function () {
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
            '¿De qué equipo sos?',
            'id="idEdicion"',
            'id="seguir-equipo-bloque"',
            'O explorá todo el torneo',
        ]))->toBeTrue();
    });

    it('mantiene el buscador cerrado con sus datos y el botón de tema disponibles', function () {
        $contexto = homeBase();
        Equipo::create([
            'idCategoria' => $contexto['categoria']->id,
            'idEdicion' => $contexto['edicion']->id,
            'nombre' => 'Equipo Buscador',
            'foto' => 'equipo.png',
        ]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        // Desplegable cerrado por defecto: la lista existe pero arranca oculta.
        expect($html)->toContain('id="seguir-equipo-buscador"');
        expect($html)->toContain('Buscá tu equipo, ej: Talleres');
        expect($html)->toContain('id="seguir-equipo-lista"');
        expect($html)->toContain('id="seguir-equipo-datos"');
        expect($html)->toContain('hidden overflow-hidden rounded-2xl');
        // Override manual de tema en la navegación compartida.
        expect($html)->toContain('id="btn-tema"');
        expect($html)->toContain('Cambiar tema');
    });

    it('recuerda la edición elegida en el selector', function () {
        $contexto = homeBase();
        homePartido($contexto, homeFecha($contexto, 'Fecha 2'), now()->addDays(2)->format('Y-m-d H:i:s'));

        $html = $this->get(route('home', ['idEdicion' => $contexto['edicion']->id]))->assertOk()->getContent();

        expect(preg_match('/value="'.preg_quote($contexto['edicion']->id, '/').'"[^>]*selected/', $html))->toBe(1);
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

        expect(preg_match_all('/href="'.preg_quote($urlFixture, '/').'"/', $sinNav))->toBe(0);
    });

    it('expone los accesos del torneo con la edición activa', function () {
        $contexto = homeBase();
        homePartido($contexto, homeFecha($contexto, 'Fecha 2'), now()->addDays(2)->format('Y-m-d H:i:s'));

        $html = $this->get(route('home'))->assertOk()->getContent();

        expect($html)->toContain(route('tabla-posiciones', ['idEdicion' => $contexto['edicion']->id]));
        expect($html)->toContain(route('tabla-goleadores', ['idEdicion' => $contexto['edicion']->id]));
        expect($html)->toContain(route('instancias-finales', ['idEdicion' => $contexto['edicion']->id]));
        expect($html)->toContain(route('tabla-vallas', ['idEdicion' => $contexto['edicion']->id]));
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
            '¿De qué equipo sos?',
            'id="idEdicion"',
            'id="seguir-equipo-bloque"',
            'O explorá todo el torneo',
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
