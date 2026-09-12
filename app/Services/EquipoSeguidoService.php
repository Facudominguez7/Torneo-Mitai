<?php

namespace App\Services;

use App\Models\Equipo;
use App\Models\Partido;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EquipoSeguidoService
{
    /**
     * Resuelve el equipo por nombre dentro de una edición y arma el payload
     * del endpoint público: próximo partido, último resultado y accesos a
     * fixture y tabla de posiciones. Nunca falla por falta de partidos.
     */
    public function resolver(string $nombre, int $idEdicion): array
    {
        $equipo = $this->resolverEquipo($nombre, $idEdicion);

        if ($equipo === null) {
            return ['disponible' => false];
        }

        $proximo = $this->resolverPartido($equipo, jugado: false);
        $ultimo = $this->resolverPartido($equipo, jugado: true);
        $rivales = $this->resolverRivales($equipo, $proximo, $ultimo);

        return [
            'disponible' => true,
            'equipo' => [
                'nombre' => $equipo->nombre,
                'idCategoria' => $equipo->idCategoria,
            ],
            'proximo' => $proximo !== null ? $this->armarProximo($proximo, $equipo, $rivales) : null,
            'ultimo' => $ultimo !== null ? $this->armarUltimo($ultimo, $equipo, $rivales) : null,
            'links' => $this->armarLinks($equipo, $proximo, $ultimo),
        ];
    }

    private function resolverEquipo(string $nombre, int $idEdicion): ?Equipo
    {
        return Equipo::query()
            ->where('idEdicion', $idEdicion)
            ->where('nombre', self::normalizarNombre($nombre))
            ->orderBy('id')
            ->first();
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

    private function resolverPartido(Equipo $equipo, bool $jugado): ?Partido
    {
        return Partido::query()
            ->where('idEdicion', $equipo->idEdicion)
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

    private function armarLinks(Equipo $equipo, ?Partido $proximo, ?Partido $ultimo): array
    {
        $idGrupo = $proximo?->idGrupo ?? $ultimo?->idGrupo;

        if ($idGrupo === null && $proximo === null && $ultimo === null) {
            $idGrupo = $equipo->grupos()->first()?->id;
        }

        return [
            'fixture' => route('fixture', ['idEdicion' => $equipo->idEdicion, 'equipo' => $equipo->nombre]),
            'tabla' => route('tabla-posiciones', array_filter([
                'idEdicion' => $equipo->idEdicion,
                'idCategoria' => $equipo->idCategoria,
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
