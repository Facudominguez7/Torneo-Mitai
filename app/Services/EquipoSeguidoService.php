<?php

namespace App\Services;

use App\Models\Equipo;
use App\Models\Partido;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EquipoSeguidoService
{
    public function __construct(
        private readonly EquiposDeEdicionService $equiposDeEdicion,
    ) {}

    /**
     * Resuelve el equipo por nombre dentro de una edición y arma el payload
     * del endpoint público: próximo partido, último resultado y accesos a
     * fixture y tabla de posiciones. Nunca falla por falta de partidos.
     *
     * La resolución usa el padrón de participantes de la edición (unión de
     * equipo_ediciones y equipos.idEdicion), así un equipo heredado de otra
     * edición también resuelve. La edición pedida gobierna partidos, links y
     * la categoría devuelta: los atributos del modelo pueden apuntar a la
     * edición de origen del equipo.
     *
     * Enmienda #100: si llega idCategoria, la resolución se restringe a esa
     * categoría de la edición; sin ella se conserva el primer match por id
     * (retrocompatible).
     */
    public function resolver(string $nombre, int $idEdicion, ?int $idCategoria = null): array
    {
        $participante = $this->resolverParticipante($nombre, $idEdicion, $idCategoria);

        if ($participante === null) {
            return ['disponible' => false];
        }

        [$equipo, $idCategoriaEdicion] = $participante;

        $proximo = $this->resolverPartido($equipo, $idEdicion, jugado: false);
        $ultimo = $this->resolverPartido($equipo, $idEdicion, jugado: true);
        $rivales = $this->resolverRivales($equipo, $proximo, $ultimo);

        return [
            'disponible' => true,
            'equipo' => [
                'nombre' => $equipo->nombre,
                'idCategoria' => $idCategoriaEdicion,
            ],
            'proximo' => $proximo !== null ? $this->armarProximo($proximo, $equipo, $rivales) : null,
            'ultimo' => $ultimo !== null ? $this->armarUltimo($ultimo, $equipo, $rivales) : null,
            'links' => $this->armarLinks($equipo, $idEdicion, $idCategoriaEdicion, $proximo, $ultimo),
        ];
    }

    /**
     * Devuelve el equipo resuelto y su categoría de la edición pedida.
     *
     * @return array{0: Equipo, 1: int|null}|null
     */
    private function resolverParticipante(string $nombre, int $idEdicion, ?int $idCategoria): ?array
    {
        $inscripto = $this->equiposDeEdicion
            ->participantes($idEdicion)
            ->where('nombre', self::normalizarNombre($nombre))
            ->when(
                $idCategoria !== null,
                fn (Collection $inscriptos) => $inscriptos->where('idCategoria', $idCategoria)
            )
            ->sortBy('id')
            ->first();

        if ($inscripto === null) {
            return null;
        }

        $equipo = Equipo::find($inscripto['id']);

        return $equipo === null ? null : [$equipo, $inscripto['idCategoria']];
    }

    /**
     * Normaliza el nombre igual que el mutator Equipo::setNombreAttribute
     * (colapsar espacios + Title Case mbstring + siglas en mayúscula) para
     * que la resolución comparta la regla de persistencia. Vive acá porque
     * el modelo no es una superficie editable de este change.
     */
    public static function normalizarNombre(string $nombre): string
    {
        $nombre = trim((string) preg_replace('/\s+/', ' ', $nombre));

        $minusculas = mb_strtolower($nombre, 'UTF-8');
        $partes = preg_split('/(\s+)/u', $minusculas, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($partes as $i => $parte) {
            if (preg_match('/^\s+$/u', $parte)) {
                continue;
            }

            $partes[$i] = preg_replace_callback(
                '/\p{L}/u',
                fn ($coincidencia) => mb_strtoupper($coincidencia[0], 'UTF-8'),
                $parte,
                1
            );
        }
        $titulo = implode('', $partes);

        foreach (['CA', 'AF', 'FC', 'RC'] as $sigla) {
            $patron = '/(?<!\p{L})'.preg_quote(ucfirst(mb_strtolower($sigla, 'UTF-8')), '/').'(?!\p{L})/u';
            $titulo = preg_replace($patron, $sigla, $titulo);
        }

        return $titulo;
    }

    private function resolverPartido(Equipo $equipo, int $idEdicion, bool $jugado): ?Partido
    {
        return Partido::query()
            ->where('idEdicion', $idEdicion)
            ->where(fn ($query) => $query
                ->where('idEquipoLocal', $equipo->id)
                ->orWhere('idEquipoVisitante', $equipo->id))
            ->where(function ($query) use ($jugado) {
                if ($jugado) {
                    $query->where('jugado', 1);

                    return;
                }
                $query->where('jugado', 0)->orWhereNull('jugado');
            })
            ->whereNotNull('horario_datetime')
            ->when(! $jugado, fn ($query) => $query->where('horario_datetime', '>=', now()))
            ->orderBy('horario_datetime', $jugado ? 'desc' : 'asc')
            ->orderBy('idFechas', $jugado ? 'desc' : 'asc')
            ->with('fecha')
            ->first();
    }

    private function resolverRivales(Equipo $equipo, ?Partido $proximo, ?Partido $ultimo): Collection
    {
        $idsRivales = collect([$proximo, $ultimo])
            ->filter()
            ->map(fn (Partido $partido) => $this->idRival($partido, $equipo))
            ->unique()
            ->values();

        if ($idsRivales->isEmpty()) {
            return collect();
        }

        return Equipo::query()->whereIn('id', $idsRivales)->pluck('nombre', 'id');
    }

    private function armarProximo(Partido $partido, Equipo $equipo, Collection $rivales): array
    {
        return [
            'rival' => $rivales->get($this->idRival($partido, $equipo)),
            'esLocal' => $this->esLocal($partido, $equipo),
            'fecha_nombre' => $partido->fecha?->nombre,
            'horario' => Carbon::parse($partido->horario_datetime)
                ->locale(config('app.locale'))
                ->translatedFormat('D d/m · H:i'),
            'cancha' => $partido->cancha !== null ? 'Cancha '.$partido->cancha : null,
        ];
    }

    private function armarUltimo(Partido $partido, Equipo $equipo, Collection $rivales): array
    {
        $esLocal = $this->esLocal($partido, $equipo);

        return [
            'rival' => $rivales->get($this->idRival($partido, $equipo)),
            'esLocal' => $esLocal,
            'goles_favor' => (int) ($esLocal ? $partido->golesEquipoLocal : $partido->golesEquipoVisitante),
            'goles_contra' => (int) ($esLocal ? $partido->golesEquipoVisitante : $partido->golesEquipoLocal),
        ];
    }

    private function armarLinks(Equipo $equipo, int $idEdicion, ?int $idCategoria, ?Partido $proximo, ?Partido $ultimo): array
    {
        $idGrupo = $proximo?->idGrupo ?? $ultimo?->idGrupo;

        if ($idGrupo === null && $proximo === null && $ultimo === null) {
            $idGrupo = $equipo->grupos()->first()?->id;
        }

        return [
            'fixture' => route('fixture', ['idEdicion' => $idEdicion, 'equipo' => $equipo->nombre]),
            'tabla' => route('tabla-posiciones', array_filter([
                'idEdicion' => $idEdicion,
                'idCategoria' => $idCategoria,
                'idGrupo' => $idGrupo,
            ])),
        ];
    }

    private function esLocal(Partido $partido, Equipo $equipo): bool
    {
        return (int) $partido->idEquipoLocal === $equipo->id;
    }

    private function idRival(Partido $partido, Equipo $equipo): int
    {
        return $this->esLocal($partido, $equipo)
            ? (int) $partido->idEquipoVisitante
            : (int) $partido->idEquipoLocal;
    }
}
