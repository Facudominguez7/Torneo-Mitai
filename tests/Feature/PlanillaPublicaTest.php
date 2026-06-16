<?php

namespace Tests\Feature;

use App\Constants\PartidoConstants;
use App\Models\Categoria;
use App\Models\Copa;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fase;
use App\Models\Fecha;
use App\Models\GolArbitroReportado;
use App\Models\Grupo;
use App\Models\InstanciaFinal;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanillaPublicaTest extends TestCase
{
    use RefreshDatabase;

    private function crearContextoPlanillaPublica(): array
    {
        $edicion = Edicion::create([
            'nombre' => 'Edicion Planilla Publica',
        ]);

        $categoria = Categoria::create([
            'idEdicion' => $edicion->id,
            'nombreCategoria' => 'Sub 12',
        ]);

        $fecha = Fecha::create([
            'idCategoria' => $categoria->id,
            'idEdicion' => $edicion->id,
            'nombre' => 'Fecha 1',
        ]);

        $grupo = Grupo::create([
            'idCategoria' => $categoria->id,
            'idEdicion' => $edicion->id,
            'nombre' => 'Grupo A',
        ]);

        $equipoLocal = Equipo::create([
            'idCategoria' => $categoria->id,
            'idEdicion' => $edicion->id,
            'nombre' => 'Mitai Azul',
            'foto' => 'azul.png',
        ]);

        $equipoVisitante = Equipo::create([
            'idCategoria' => $categoria->id,
            'idEdicion' => $edicion->id,
            'nombre' => 'Mitai Blanco',
            'foto' => 'blanco.png',
        ]);

        EquipoEdicion::create([
            'idEdicion' => $edicion->id,
            'idEquipo' => $equipoLocal->id,
            'idCategoria' => $categoria->id,
        ]);

        EquipoEdicion::create([
            'idEdicion' => $edicion->id,
            'idEquipo' => $equipoVisitante->id,
            'idCategoria' => $categoria->id,
        ]);

        $partido = Partido::create([
            'idCategoria' => $categoria->id,
            'idFechas' => $fecha->id,
            'idEquipoLocal' => $equipoLocal->id,
            'idEquipoVisitante' => $equipoVisitante->id,
            'idGrupo' => $grupo->id,
            'idEdicion' => $edicion->id,
            'golesEquipoLocal' => 2,
            'golesEquipoVisitante' => 1,
            'cancha' => 1,
            'jugado' => 1,
            'horario_datetime' => '2026-04-01 10:00:00',
        ]);

        $copa = Copa::create([
            'idEdicion' => $edicion->id,
            'nombre' => 'Copa Oro',
        ]);

        $fase = Fase::create([
            'nombre' => 'Semifinal',
        ]);

        $instanciaFinal = InstanciaFinal::create([
            'idCategoria' => $categoria->id,
            'idCopa' => $copa->id,
            'idEdicion' => $edicion->id,
            'idFase' => $fase->id,
            'idEquipoLocal' => $equipoLocal->id,
            'idEquipoVisitante' => $equipoVisitante->id,
            'golesEquipoLocal' => 0,
            'golesEquipoVisitante' => 0,
            'horario' => '2026-04-10 18:00:00',
            'cancha' => '2',
        ]);

        return compact('edicion', 'categoria', 'fecha', 'grupo', 'equipoLocal', 'equipoVisitante', 'partido', 'copa', 'fase', 'instanciaFinal');
    }

    private function crearJugadorPlanillaPublica(string $dni, string $nombre, string $apellido): Jugador
    {
        return Jugador::create([
            'dni' => $dni,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'goles_totales' => 0,
            'partidos_totales' => 0,
            'tarjetas_rojas_totales' => 0,
        ]);
    }

    public function test_devuelve_una_planilla_publica_readonly_para_partidos_regulares_sin_campos_sensibles(): void
    {
        $contexto = $this->crearContextoPlanillaPublica();
        $contexto['partido']->update([
            'goles_confirmados_at' => '2026-03-18 20:30:00',
        ]);

        $this->crearJugadorPlanillaPublica('40100001', 'Juan', 'Perez');
        $this->crearJugadorPlanillaPublica('40100002', 'Pedro', 'Lopez');

        $registroLocal = PlanillaJugador::create([
            'partido_id' => $contexto['partido']->id,
            'partido_type' => PartidoConstants::CLASS_PARTIDO,
            'dni_jugador' => '40100001',
            'idEquipo' => $contexto['equipoLocal']->id,
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'numero_camiseta' => 9,
            'fecha_nacimiento' => '2014-02-01',
            'goles' => 2,
            'asistio' => true,
        ]);

        $registroVisitante = PlanillaJugador::create([
            'partido_id' => $contexto['partido']->id,
            'partido_type' => PartidoConstants::CLASS_PARTIDO,
            'dni_jugador' => '40100002',
            'idEquipo' => $contexto['equipoVisitante']->id,
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'numero_camiseta' => 5,
            'fecha_nacimiento' => '2014-03-05',
            'goles' => 0,
            'asistio' => false,
        ]);

        $registroLocal->forceFill(['updated_at' => '2026-03-18 19:45:00'])->save();
        $registroVisitante->forceFill(['updated_at' => '2026-03-18 20:15:00'])->save();

        $response = $this->getJson(route('planillas-publicas.show', [
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'publicToken' => $contexto['partido']->public_token,
        ]));

        $response->assertOk()
            ->assertJsonPath('data.partido.tipo', PartidoConstants::TIPO_PARTIDO_NORMAL)
            ->assertJsonPath('data.partido.categoria', 'Sub 12')
            ->assertJsonPath('data.partido.equipo_local', 'Mitai Azul')
            ->assertJsonPath('data.partido.equipo_visitante', 'Mitai Blanco')
            ->assertJsonPath('data.goles_confirmados', true)
            ->assertJsonPath('data.goles_confirmados_at', '18/03/2026 20:30')
            ->assertJsonPath('data.ultima_actualizacion', '18/03/2026 20:15')
            ->assertJsonPath('data.local.0.nombre', 'Juan')
            ->assertJsonPath('data.local.0.apellido', 'Perez')
            ->assertJsonPath('data.local.0.numero_camiseta', 9)
            ->assertJsonPath('data.local.0.goles', 2)
            ->assertJsonPath('data.local.0.asistencia', true)
            ->assertJsonPath('data.visitante.0.nombre', 'Pedro')
            ->assertJsonPath('data.visitante.0.apellido', 'Lopez')
            ->assertJsonPath('data.visitante.0.numero_camiseta', 5)
            ->assertJsonPath('data.visitante.0.goles', 0)
            ->assertJsonPath('data.visitante.0.asistencia', false)
            ->assertJsonPath('meta.sin_planilla', false)
            ->assertJsonMissingPath('data.local.0.dni')
            ->assertJsonMissingPath('data.local.0.dni_jugador')
            ->assertJsonMissingPath('data.local.0.fecha_nacimiento')
            ->assertJsonMissingPath('data.visitante.0.dni')
            ->assertJsonMissingPath('data.visitante.0.dni_jugador')
            ->assertJsonMissingPath('data.visitante.0.fecha_nacimiento')
            ->assertJsonMissingPath('data.partido.id')
            ->assertJsonMissingPath('data.partido.idEdicion');
    }

    public function test_devuelve_planilla_vacia_para_instancias_finales_sin_registros(): void
    {
        $contexto = $this->crearContextoPlanillaPublica();

        $response = $this->getJson(route('planillas-publicas.show', [
            'tipoPartido' => PartidoConstants::TIPO_INSTANCIA_FINAL,
            'publicToken' => $contexto['instanciaFinal']->public_token,
        ]));

        $response->assertOk()
            ->assertJsonPath('data.partido.tipo', PartidoConstants::TIPO_INSTANCIA_FINAL)
            ->assertJsonPath('data.partido.equipo_local', 'Mitai Azul')
            ->assertJsonPath('data.partido.equipo_visitante', 'Mitai Blanco')
            ->assertJsonPath('data.goles_confirmados', false)
            ->assertJsonPath('data.goles_confirmados_at', null)
            ->assertJsonPath('data.ultima_actualizacion', null)
            ->assertJsonPath('data.local', [])
            ->assertJsonPath('data.visitante', [])
            ->assertJsonPath('meta.sin_planilla', true)
            ->assertJsonMissingPath('data.partido.id')
            ->assertJsonMissingPath('data.partido.idEdicion');
    }

    public function test_retorna_404_para_token_inexistente(): void
    {
        $response = $this->getJson(route('planillas-publicas.show', [
            'tipoPartido' => 'partido',
            'publicToken' => '00000000-0000-0000-0000-000000000000',
        ]));

        $response->assertStatus(404)
            ->assertJsonPath('message', 'La planilla solicitada no existe.');
    }

    public function test_retorna_404_para_token_malformado(): void
    {
        $response = $this->getJson(route('planillas-publicas.show', [
            'tipoPartido' => 'partido',
            'publicToken' => 'not-a-valid-uuid',
        ]));

        $response->assertStatus(404)
            ->assertJsonPath('message', 'La planilla solicitada no existe.');
    }

    public function test_retorna_404_para_tipo_partido_invalido(): void
    {
        $contexto = $this->crearContextoPlanillaPublica();

        $response = $this->getJson(route('planillas-publicas.show', [
            'tipoPartido' => 'tipo-inexistente',
            'publicToken' => $contexto['partido']->public_token,
        ]));

        $response->assertStatus(404)
            ->assertJsonPath('message', 'La planilla solicitada no existe.');
    }

    public function test_retorna_404_cuando_el_token_valido_no_corresponde_al_tipo_partido_consultado(): void
    {
        $contexto = $this->crearContextoPlanillaPublica();

        $respuestaConTokenDePartido = $this->getJson(route('planillas-publicas.show', [
            'tipoPartido' => PartidoConstants::TIPO_INSTANCIA_FINAL,
            'publicToken' => $contexto['partido']->public_token,
        ]));

        $respuestaConTokenDeInstanciaFinal = $this->getJson(route('planillas-publicas.show', [
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'publicToken' => $contexto['instanciaFinal']->public_token,
        ]));

        $respuestaConTokenDePartido->assertStatus(404)
            ->assertJsonPath('message', 'La planilla solicitada no existe.');

        $respuestaConTokenDeInstanciaFinal->assertStatus(404)
            ->assertJsonPath('message', 'La planilla solicitada no existe.');
    }

    public function test_no_expone_ids_internos_en_payload(): void
    {
        $contexto = $this->crearContextoPlanillaPublica();
        $contexto['partido']->update([
            'goles_confirmados_at' => '2026-03-18 20:30:00',
        ]);

        $this->crearJugadorPlanillaPublica('40100001', 'Juan', 'Perez');
        PlanillaJugador::create([
            'partido_id' => $contexto['partido']->id,
            'partido_type' => PartidoConstants::CLASS_PARTIDO,
            'dni_jugador' => '40100001',
            'idEquipo' => $contexto['equipoLocal']->id,
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'goles' => 1,
            'asistio' => true,
        ]);

        $response = $this->getJson(route('planillas-publicas.show', [
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'publicToken' => $contexto['partido']->public_token,
        ]));

        $response->assertOk();

        $data = $response->json();
        $this->assertArrayNotHasKey('id', $data['data']['partido'] ?? []);
        $this->assertArrayNotHasKey('idEdicion', $data['data']['partido'] ?? []);
        $this->assertArrayNotHasKey('id', $data['data']['local'][0] ?? []);
    }

    public function test_instancia_final_con_public_token_funciona_correctamente(): void
    {
        $contexto = $this->crearContextoPlanillaPublica();

        $this->crearJugadorPlanillaPublica('40100003', 'Carlos', 'Gomez');
        PlanillaJugador::create([
            'partido_id' => $contexto['instanciaFinal']->id,
            'partido_type' => PartidoConstants::CLASS_INSTANCIA_FINAL,
            'dni_jugador' => '40100003',
            'idEquipo' => $contexto['equipoLocal']->id,
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'numero_camiseta' => 10,
            'goles' => 3,
            'asistio' => true,
        ]);

        $response = $this->getJson(route('planillas-publicas.show', [
            'tipoPartido' => PartidoConstants::TIPO_INSTANCIA_FINAL,
            'publicToken' => $contexto['instanciaFinal']->public_token,
        ]));

        $response->assertOk()
            ->assertJsonPath('data.local.0.nombre', 'Carlos')
            ->assertJsonPath('data.local.0.numero_camiseta', 10)
            ->assertJsonPath('data.local.0.goles', 3)
            ->assertJsonPath('meta.sin_planilla', false);
    }

    public function test_devuelve_numero_camiseta_nulo_sin_inventar_valores_por_defecto(): void
    {
        $contexto = $this->crearContextoPlanillaPublica();

        $this->crearJugadorPlanillaPublica('40100004', 'Lucia', 'Martinez');

        PlanillaJugador::create([
            'partido_id' => $contexto['partido']->id,
            'partido_type' => PartidoConstants::CLASS_PARTIDO,
            'dni_jugador' => '40100004',
            'idEquipo' => $contexto['equipoLocal']->id,
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'numero_camiseta' => null,
            'goles' => 1,
            'asistio' => true,
        ]);

        $response = $this->getJson(route('planillas-publicas.show', [
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'publicToken' => $contexto['partido']->public_token,
        ]));

        $response->assertOk()
            ->assertJsonPath('data.local.0.nombre', 'Lucia')
            ->assertJsonPath('data.local.0.numero_camiseta', null)
            ->assertJsonPath('data.local.0.goles', 1)
            ->assertJsonPath('data.local.0.asistencia', true);
    }

    public function test_expone_goles_arbitrales_separados_y_sus_inconsistencias(): void
    {
        $contexto = $this->crearContextoPlanillaPublica();

        $jugador = $this->crearJugadorPlanillaPublica('40100005', 'Mario', 'Suarez');

        $planilla = PlanillaJugador::create([
            'partido_id' => $contexto['partido']->id,
            'partido_type' => PartidoConstants::CLASS_PARTIDO,
            'dni_jugador' => $jugador->dni,
            'idEquipo' => $contexto['equipoLocal']->id,
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'numero_camiseta' => 11,
            'fecha_nacimiento' => '2014-04-10',
            'goles' => 1,
            'asistio' => true,
        ]);

        GolArbitroReportado::create([
            'partido_id' => $contexto['partido']->id,
            'partido_type' => PartidoConstants::CLASS_PARTIDO,
            'idEdicion' => $contexto['edicion']->id,
            'idEquipo' => $contexto['equipoLocal']->id,
            'idPlanillaJugador' => $planilla->id,
            'numero_camiseta' => 11,
            'goles_reportados' => 1,
            'es_inconsistencia' => false,
        ]);

        GolArbitroReportado::create([
            'partido_id' => $contexto['partido']->id,
            'partido_type' => PartidoConstants::CLASS_PARTIDO,
            'idEdicion' => $contexto['edicion']->id,
            'idEquipo' => $contexto['equipoVisitante']->id,
            'numero_camiseta' => 99,
            'goles_reportados' => 1,
            'es_inconsistencia' => true,
            'motivo_inconsistencia' => 'El número de camiseta N° 99 reportado por los árbitros no figura en la planilla oficial de este equipo. El gol no se vinculó a ningún jugador.',
        ]);

        $response = $this->getJson(route('planillas-publicas.show', [
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'publicToken' => $contexto['partido']->public_token,
        ]));

        $response->assertOk()
            ->assertJsonPath('data.goles_arbitros.local.0.numero_camiseta', 11)
            ->assertJsonPath('data.goles_arbitros.local.0.goles_reportados', 1)
            ->assertJsonPath('data.goles_arbitros.local.0.jugador_oficial', 'Suarez, Mario')
            ->assertJsonPath('data.goles_arbitros.local.0.es_inconsistencia', false)
            ->assertJsonPath('data.goles_arbitros.visitante.0.numero_camiseta', 99)
            ->assertJsonPath('data.goles_arbitros.visitante.0.es_inconsistencia', true)
            ->assertJsonPath('data.goles_arbitros.visitante.0.motivo_inconsistencia', 'El número de camiseta N° 99 reportado por los árbitros no figura en la planilla oficial de este equipo. El gol no se vinculó a ningún jugador.')
            ->assertJsonPath('data.goles_arbitros.hay_inconsistencias', true)
            ->assertJsonMissingPath('data.goles_arbitros.local.0.idPlanillaJugador');
    }

    public function test_no_vincula_goles_arbitrales_si_la_camiseta_existe_pero_el_jugador_no_tuvo_asistencia(): void
    {
        $contexto = $this->crearContextoPlanillaPublica();

        $jugador = $this->crearJugadorPlanillaPublica('40100006', 'Mateo', 'Rivero');

        $planilla = PlanillaJugador::create([
            'partido_id' => $contexto['partido']->id,
            'partido_type' => PartidoConstants::CLASS_PARTIDO,
            'dni_jugador' => $jugador->dni,
            'idEquipo' => $contexto['equipoLocal']->id,
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'numero_camiseta' => 8,
            'fecha_nacimiento' => '2014-05-20',
            'goles' => 0,
            'asistio' => false,
        ]);

        GolArbitroReportado::create([
            'partido_id' => $contexto['partido']->id,
            'partido_type' => PartidoConstants::CLASS_PARTIDO,
            'idEdicion' => $contexto['edicion']->id,
            'idEquipo' => $contexto['equipoLocal']->id,
            'idPlanillaJugador' => $planilla->id,
            'numero_camiseta' => 8,
            'goles_reportados' => 2,
            'es_inconsistencia' => false,
        ]);

        $response = $this->getJson(route('planillas-publicas.show', [
            'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
            'publicToken' => $contexto['partido']->public_token,
        ]));

        $response->assertOk()
            ->assertJsonPath('data.goles_arbitros.local.0.numero_camiseta', 8)
            ->assertJsonPath('data.goles_arbitros.local.0.goles_reportados', 2)
            ->assertJsonPath('data.goles_arbitros.local.0.es_inconsistencia', true)
            ->assertJsonPath('data.goles_arbitros.local.0.motivo_inconsistencia', 'El jugador con la camiseta N° 8 figura en la planilla pero no está marcado como presente en este partido, por eso el gol no se vinculó.')
            ->assertJsonPath('data.goles_arbitros.local.0.jugador_oficial', null)
            ->assertJsonPath('data.goles_arbitros.hay_inconsistencias', true);
    }

    public function test_fixture_renderiza_sin_explotar_si_falta_public_token(): void
    {
        $contexto = $this->crearContextoPlanillaPublica();

        $partido = $contexto['partido']->replicate()->forceFill([
            'id' => $contexto['partido']->id,
            'public_token' => null,
            'nombre_local' => $contexto['equipoLocal']->nombre,
            'nombre_visitante' => $contexto['equipoVisitante']->nombre,
            'foto_local' => $contexto['equipoLocal']->foto,
            'foto_visitante' => $contexto['equipoVisitante']->foto,
            'nombre_categoria' => $contexto['categoria']->nombreCategoria,
        ]);
        $partido->setRelation('fecha', $contexto['fecha']);

        $html = view('layouts.fixture', [
            'nombreFecha' => $contexto['fecha']->nombre,
            'nombreCategoria' => $contexto['categoria']->nombreCategoria,
            'grupos' => collect([$contexto['grupo']]),
            'ediciones' => collect([$contexto['edicion']]),
            'EdicionSeleccionada' => $contexto['edicion'],
            'categorias' => collect([$contexto['categoria']]),
            'idCategoria' => $contexto['categoria']->id,
            'CategoriaSeleccionada' => $contexto['categoria'],
            'fechas' => collect([$contexto['fecha']]),
            'partidos' => collect([$partido]),
        ])->render();

        $this->assertStringContainsString('Planilla no disponible', $html);
        $this->assertStringNotContainsString('planillas-publicas/partido/', $html);
    }

    public function test_instancias_finales_renderiza_sin_explotar_si_falta_public_token(): void
    {
        $contexto = $this->crearContextoPlanillaPublica();

        $instanciaFinal = $contexto['instanciaFinal']->replicate()->forceFill([
            'id' => $contexto['instanciaFinal']->id,
            'public_token' => null,
            'nombre_categoria' => $contexto['categoria']->nombreCategoria,
            'nombre_copa' => $contexto['copa']->nombre,
            'nombre_fase' => $contexto['fase']->nombre,
            'orden_copa' => 1,
            'orden_fase' => 1,
        ]);
        $instanciaFinal->setRelation('equipoLocal', $contexto['equipoLocal']);
        $instanciaFinal->setRelation('equipoVisitante', $contexto['equipoVisitante']);

        $html = view('layouts.instancias-finales', [
            'ediciones' => collect([$contexto['edicion']]),
            'ultimaEdicion' => $contexto['edicion'],
            'EdicionSeleccionada' => $contexto['edicion'],
            'partidos' => collect([$instanciaFinal]),
            'categorias' => collect([$contexto['categoria']]),
            'selectedCategoria' => null,
            'copas' => collect([$contexto['copa']]),
            'selectedCopa' => null,
            'fases' => collect([$contexto['fase']]),
            'selectedFase' => null,
        ])->render();

        $this->assertStringContainsString('Planilla no disponible', $html);
        $this->assertStringNotContainsString('planillas-publicas/instancia_final/', $html);
    }
}
