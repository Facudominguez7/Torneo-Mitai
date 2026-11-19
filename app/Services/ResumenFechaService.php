<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Fecha;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use Carbon\Carbon;

/**
 * Arma el resumen de resultados de una fecha de liga de la última edición: un único correo
 * con todos los marcadores cargados, en lugar de un aviso por partido.
 */
class ResumenFechaService
{
    /**
     * El resumen siempre corresponde a la edición más reciente, aunque la interfaz mande otra.
     */
    public function idUltimaEdicion(): ?int
    {
        $idEdicion = Edicion::query()->orderByDesc('id')->value('id');

        return $idEdicion !== null ? (int) $idEdicion : null;
    }

    /**
     * @return array<string, mixed>|null null si la fecha no es de esa edición o no tiene resultados cargados
     */
    public function armar(int $idEdicion, int $idFecha, ?int $idCategoria = null): ?array
    {
        $fecha = Fecha::query()
            ->where('idEdicion', $idEdicion)
            ->whereKey($idFecha)
            ->first();

        if (! $fecha) {
            return null;
        }

        // Sin categoría ("Todas las categorías") entran todas, aunque la fecha tenga una propia.
        $idCategoriaFinal = ($idCategoria ?? 0) > 0 ? (int) $idCategoria : 0;

        $consulta = Partido::query()
            ->with(['equipoLocal:id,nombre,foto', 'equipoVisitante:id,nombre,foto', 'grupo:id,nombre', 'categoria:id,nombreCategoria'])
            ->where('idEdicion', $idEdicion)
            ->where('idFechas', $idFecha)
            ->whereNotNull('golesEquipoLocal')
            ->whereNotNull('golesEquipoVisitante');

        if ($idCategoriaFinal > 0) {
            $consulta->where('idCategoria', $idCategoriaFinal);
        }

        $partidos = $consulta
            ->orderBy('idGrupo')
            ->orderBy('horario_datetime')
            ->orderBy('id')
            ->get();

        if ($partidos->isEmpty()) {
            return null;
        }

        $edicionNombre = Edicion::query()
            ->whereKey($idEdicion)
            ->value('nombre') ?? 'Edición vigente';

        $categoriaNombre = null;
        if ($idCategoriaFinal > 0) {
            $categoriaNombre = Categoria::query()
                ->whereKey($idCategoriaFinal)
                ->value('nombreCategoria');
        }

        return [
            'id_edicion' => $idEdicion,
            'id_fecha' => $idFecha,
            'id_categoria' => $idCategoriaFinal > 0 ? $idCategoriaFinal : null,
            'edicion_nombre' => $edicionNombre,
            'fecha_nombre' => $fecha->nombre,
            'categoria_nombre' => $categoriaNombre,
            'url_fixture' => '/fixture?idEdicion='.$idEdicion,
            'partidos_count' => $partidos->count(),
            'partidos' => $partidos->map(fn (Partido $partido): array => [
                'id' => $partido->id,
                'equipo_local' => $partido->equipoLocal->nombre ?? 'Local',
                'equipo_visitante' => $partido->equipoVisitante->nombre ?? 'Visitante',
                'escudo_local' => $this->urlEscudo($partido->equipoLocal->foto ?? null),
                'escudo_visitante' => $this->urlEscudo($partido->equipoVisitante->foto ?? null),
                'goles_local' => (int) $partido->golesEquipoLocal,
                'goles_visitante' => (int) $partido->golesEquipoVisitante,
                'grupo' => $this->etiquetaGrupo($partido->grupo->nombre ?? null),
                'cancha' => $partido->cancha ? ('Cancha '.$partido->cancha) : null,
                'horario' => $partido->horario_datetime
                    ? Carbon::parse($partido->horario_datetime)->format('d/m H:i')
                    : ($partido->horario ?: null),
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed>|null */
    public function armarDia(int $idEdicion, string $dia, ?int $idCategoria = null): ?array
    {
        $inicio = Carbon::createFromFormat('!Y-m-d', $dia);
        $fin = $inicio->copy()->addDay();
        $liga = Partido::query()
            ->with(['equipoLocal:id,nombre,foto', 'equipoVisitante:id,nombre,foto', 'grupo:id,nombre'])
            ->where('idEdicion', $idEdicion)
            ->when($idCategoria !== null, fn ($query) => $query->where('idCategoria', $idCategoria))
            ->where('horario_datetime', '>=', $inicio)->where('horario_datetime', '<', $fin)
            ->whereNotNull('golesEquipoLocal')->whereNotNull('golesEquipoVisitante')
            ->orderBy('horario_datetime')->orderBy('id')->get();
        $finales = InstanciaFinal::query()
            ->with(['equipoLocal:id,nombre,foto', 'equipoVisitante:id,nombre,foto', 'fase:id,nombre', 'copa:id,nombre'])
            ->where('idEdicion', $idEdicion)
            ->when($idCategoria !== null, fn ($query) => $query->where('idCategoria', $idCategoria))
            ->where('horario', '>=', $inicio)->where('horario', '<', $fin)
            ->whereNotNull('golesEquipoLocal')->whereNotNull('golesEquipoVisitante')
            ->orderBy('horario')->orderBy('id')->get();

        if ($liga->isEmpty() && $finales->isEmpty()) {
            return null;
        }

        $datosLiga = $liga->map(fn (Partido $partido): array => [
            'equipo_local' => $partido->equipoLocal->nombre ?? 'Local',
            'equipo_visitante' => $partido->equipoVisitante->nombre ?? 'Visitante',
            'escudo_local' => $this->urlEscudo($partido->equipoLocal->foto ?? null),
            'escudo_visitante' => $this->urlEscudo($partido->equipoVisitante->foto ?? null),
            'goles_local' => (int) $partido->golesEquipoLocal,
            'goles_visitante' => (int) $partido->golesEquipoVisitante,
            'grupo' => $this->etiquetaGrupo($partido->grupo->nombre ?? null),
            'cancha' => $partido->cancha ? 'Cancha '.$partido->cancha : null,
            'horario' => Carbon::parse($partido->horario_datetime)->format('d/m H:i'),
        ])->all();
        $datosFinales = $finales->map(function (InstanciaFinal $partido): array {
            $global = trim((string) $partido->resultadoGlobal);
            $empate = preg_match('/^(\d+)\s*-\s*(\d+)$/', $global, $marcador) === 1
                ? (int) $marcador[1] === (int) $marcador[2]
                : (int) $partido->golesEquipoLocal === (int) $partido->golesEquipoVisitante;
            $penales = null;
            if ($empate
                && $partido->penalesEquipoLocal !== null && $partido->penalesEquipoVisitante !== null
                && (int) $partido->penalesEquipoLocal !== (int) $partido->penalesEquipoVisitante) {
                $ganador = (int) $partido->penalesEquipoLocal > (int) $partido->penalesEquipoVisitante
                    ? ($partido->equipoLocal->nombre ?? 'Local') : ($partido->equipoVisitante->nombre ?? 'Visitante');
                $penales = 'Penales: '.$partido->penalesEquipoLocal.' - '.$partido->penalesEquipoVisitante.' · Ganador: '.$ganador;
            }

            return [
                'equipo_local' => $partido->equipoLocal->nombre ?? 'Local',
                'equipo_visitante' => $partido->equipoVisitante->nombre ?? 'Visitante',
                'escudo_local' => $this->urlEscudo($partido->equipoLocal->foto ?? null),
                'escudo_visitante' => $this->urlEscudo($partido->equipoVisitante->foto ?? null),
                'goles_local' => (int) $partido->golesEquipoLocal,
                'goles_visitante' => (int) $partido->golesEquipoVisitante,
                'grupo' => collect([$partido->copa->nombre ?? null, $partido->fase->nombre ?? null])->filter()->implode(' · ') ?: null,
                'cancha' => $partido->cancha ? 'Cancha '.$partido->cancha : null,
                'horario' => Carbon::parse($partido->horario)->format('d/m H:i'),
                'global' => $global !== '' ? 'Global: '.$global : null,
                'penales' => $penales,
            ];
        })->all();

        return [
            'modo' => 'dia', 'dia' => $dia, 'id_edicion' => $idEdicion, 'id_categoria' => $idCategoria,
            'edicion_nombre' => Edicion::query()->whereKey($idEdicion)->value('nombre') ?? 'Edición vigente',
            'fecha_nombre' => $inicio->format('d/m/Y'),
            'categoria_nombre' => $idCategoria !== null ? Categoria::query()->whereKey($idCategoria)->value('nombreCategoria') : null,
            'url_fixture' => '/fixture?idEdicion='.$idEdicion,
            'partidos_count' => count($datosLiga) + count($datosFinales),
            'liga' => $datosLiga, 'finales' => $datosFinales,
        ];
    }

    /**
     * Partidos programados (todavía sin resultado) de una fecha de la última edición, para avisar
     * a los suscriptores cuándo y dónde se juega. Sin categoría entran las de todas: una fecha
     * puede tener partidos de varias (la que se crea desde el alta de un partido guarda la suya).
     *
     * @return array<string, mixed>|null null si la fecha no es de esa edición o no tiene partidos por jugar
     */
    public function armarProximaFecha(int $idEdicion, int $idFecha, ?int $idCategoria = null): ?array
    {
        $fecha = Fecha::query()
            ->where('idEdicion', $idEdicion)
            ->whereKey($idFecha)
            ->first();

        if (! $fecha) {
            return null;
        }

        $idCategoriaFinal = ($idCategoria ?? 0) > 0 ? (int) $idCategoria : null;

        $consulta = Partido::query()
            ->with(['equipoLocal:id,nombre,foto', 'equipoVisitante:id,nombre,foto', 'grupo:id,nombre', 'categoria:id,nombreCategoria'])
            ->where('idEdicion', $idEdicion)
            ->where('idFechas', $idFecha)
            ->where(fn ($partidos) => $partidos->whereNull('golesEquipoLocal')->orWhereNull('golesEquipoVisitante'));

        if ($idCategoriaFinal !== null) {
            $consulta->where('idCategoria', $idCategoriaFinal);
        }

        $partidos = $consulta
            ->orderBy('horario_datetime')
            ->orderBy('cancha')
            ->orderBy('id')
            ->get();

        if ($partidos->isEmpty()) {
            return null;
        }

        return [
            'id_edicion' => $idEdicion,
            'id_fecha' => $idFecha,
            'id_categoria' => $idCategoriaFinal,
            'edicion_nombre' => Edicion::query()->whereKey($idEdicion)->value('nombre') ?? 'Edición vigente',
            'fecha_nombre' => $fecha->nombre,
            'categoria_nombre' => $idCategoriaFinal !== null
                ? Categoria::query()->whereKey($idCategoriaFinal)->value('nombreCategoria')
                : null,
            'url_fixture' => '/fixture?idEdicion='.$idEdicion,
            'partidos_count' => $partidos->count(),
            'partidos' => $partidos->map(fn (Partido $partido): array => [
                'id' => $partido->id,
                'equipo_local' => $partido->equipoLocal->nombre ?? 'Local',
                'equipo_visitante' => $partido->equipoVisitante->nombre ?? 'Visitante',
                'escudo_local' => $this->urlEscudo($partido->equipoLocal->foto ?? null),
                'escudo_visitante' => $this->urlEscudo($partido->equipoVisitante->foto ?? null),
                'grupo' => $this->etiquetaGrupo($partido->grupo->nombre ?? null),
                'categoria' => $partido->categoria->nombreCategoria ?? null,
                'cancha' => $partido->cancha ? ('Cancha '.$partido->cancha) : null,
                'horario' => $partido->horario_datetime ? $this->diaYHora(Carbon::parse($partido->horario_datetime)) : null,
            ])->values()->all(),
        ];
    }

    /** URL absoluta del escudo para los correos; sin foto se usa el logo del torneo. */
    private function urlEscudo(?string $foto): string
    {
        $foto = trim((string) $foto);

        return $foto === ''
            ? url('fotos/Logo_Mitai_SinFondo.png')
            : url('fotos/equipos/'.rawurlencode($foto));
    }

    /** "Sáb 07/11 10:30": el día de la semana ayuda a las familias a ubicar el partido. */
    private function diaYHora(Carbon $horario): string
    {
        $dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

        return $dias[$horario->dayOfWeek].' '.$horario->format('d/m H:i');
    }

    /**
     * Hay ediciones legacy donde el grupo ya se llama "Grupo Único" y otras donde es solo la letra.
     */
    private function etiquetaGrupo(?string $nombreGrupo): ?string
    {
        $nombre = trim((string) $nombreGrupo);

        if ($nombre === '') {
            return null;
        }

        return preg_match('/^grupo\b/i', $nombre) === 1 ? $nombre : 'Grupo '.$nombre;
    }
}
