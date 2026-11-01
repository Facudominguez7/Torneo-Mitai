<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Fecha;
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

        $idCategoriaFinal = $idCategoria ?: (int) $fecha->idCategoria;

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

    /**
     * Partidos programados (todavía sin resultado) de una fecha de la última edición, para avisar
     * a los suscriptores cuándo y dónde se juega.
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

        $idCategoriaFinal = $idCategoria ?: (int) $fecha->idCategoria;

        $consulta = Partido::query()
            ->with(['equipoLocal:id,nombre,foto', 'equipoVisitante:id,nombre,foto', 'grupo:id,nombre'])
            ->where('idEdicion', $idEdicion)
            ->where('idFechas', $idFecha)
            ->where(fn ($partidos) => $partidos->whereNull('golesEquipoLocal')->orWhereNull('golesEquipoVisitante'));

        if ($idCategoriaFinal > 0) {
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
            'id_categoria' => $idCategoriaFinal > 0 ? $idCategoriaFinal : null,
            'edicion_nombre' => Edicion::query()->whereKey($idEdicion)->value('nombre') ?? 'Edición vigente',
            'fecha_nombre' => $fecha->nombre,
            'categoria_nombre' => $idCategoriaFinal > 0
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
