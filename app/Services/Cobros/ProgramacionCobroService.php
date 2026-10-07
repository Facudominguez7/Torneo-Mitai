<?php

namespace App\Services\Cobros;

use App\Models\Categoria;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Preparación de altas administrativas con cobros apagados.
 * La emisión activa falla cerrada hasta completar los cortes dependientes.
 * Sin conexión a rutas ni generación sobre partidos preexistentes.
 */
class ProgramacionCobroService
{
    private const ID_GLOBAL = 1;

    private const ERROR_FILA_AUSENTE = 'La configuración global de cobros no está inicializada (falta la fila id 1).';

    /**
     * Alta de partido de fase regular o interzonal (según es_interzonal).
     *
     * @param  array<string, mixed>  $datos  Campos conocidos del alta; los demás se ignoran.
     */
    public function crearPartido(User $actor, array $datos): Partido
    {
        /** @var Partido $padre */
        $padre = $this->crearAltasConCargos($actor, $datos, Partido::class);

        return $padre;
    }

    /**
     * Alta de instancia final.
     *
     * @param  array<string, mixed>  $datos  Campos conocidos del alta; los demás se ignoran.
     */
    public function crearInstanciaFinal(User $actor, array $datos): InstanciaFinal
    {
        /** @var InstanciaFinal $padre */
        $padre = $this->crearAltasConCargos($actor, $datos, InstanciaFinal::class);

        return $padre;
    }

    /**
     * Alta administrativa sin cobros: la emisión activa permanece cerrada
     * hasta incorporar su transacción y auditoría en el corte dependiente.
     *
     * @param  array<string, mixed>  $datos
     */
    private function crearAltasConCargos(User $actor, array $datos, string $clasePadre): Partido|InstanciaFinal
    {
        return DB::transaction(function () use ($actor, $datos, $clasePadre): Partido|InstanciaFinal {
            $this->resolverActorAdmin($actor);
            $configuracion = $this->obtenerConfiguracionBloqueada();
            $contexto = $this->validarContextoMonetario($datos);

            if ($configuracion->activado_at !== null) {
                throw new RuntimeException('La emisión activa de cargos aún no está integrada.');
            }

            return $this->crearPadre($datos, $clasePadre, $contexto);
        });
    }

    /**
     * Autorización fresca: exige actor persistido y rol vigente en DB.
     * No se confía en el rol ni el id de un objeto local transitorio o stale.
     */
    private function resolverActorAdmin(User $actor): User
    {
        $mensaje = 'Solo un administrador puede programar altas con cobro de planillas.';

        // Rechaza objetos transitorios, aunque fingan el id de un admin persistido.
        if (! $actor->exists || $actor->isDirty($actor->getKeyName())) {
            throw new AuthorizationException($mensaje);
        }

        $actorVigente = User::query()->lockForUpdate()->find($actor->getKey(), ['id', 'name', 'rol']);

        if ($actorVigente === null || ! $actorVigente->isAdmin()) {
            throw new AuthorizationException($mensaje);
        }

        return $actorVigente;
    }

    /**
     * Fila global bloqueada antes de crear el padre.
     * Falla cerrado: sin fila no hay alta, sin recreación silenciosa.
     */
    private function obtenerConfiguracionBloqueada(): ConfiguracionCobroPlanilla
    {
        try {
            $configuracion = ConfiguracionCobroPlanilla::query()
                ->whereKey(self::ID_GLOBAL)
                ->lockForUpdate()
                ->first();
        } catch (QueryException) {
            throw new RuntimeException(self::ERROR_FILA_AUSENTE);
        }

        if ($configuracion === null) {
            throw new RuntimeException(self::ERROR_FILA_AUSENTE);
        }

        return $configuracion;
    }

    /**
     * Contexto monetario: equipos distintos y existentes, categoría de la
     * edición y ambos equipos inscriptos en ese ámbito. Se resuelve con
     * consultas agrupadas, sin N+1.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function validarContextoMonetario(array $datos): array
    {
        $idLocal = $this->enteroRequerido($datos, 'idEquipoLocal');
        $idVisitante = $this->enteroRequerido($datos, 'idEquipoVisitante');
        $idEdicion = $this->enteroRequerido($datos, 'idEdicion');
        $idCategoria = $this->enteroRequerido($datos, 'idCategoria');

        if ($idLocal === $idVisitante) {
            throw ValidationException::withMessages([
                'idEquipoVisitante' => ['El equipo visitante debe ser distinto del equipo local para generar la cuota.'],
            ]);
        }

        $equipos = Equipo::query()->whereKey([$idLocal, $idVisitante])->get(['id', 'nombre']);

        if ($equipos->count() !== 2) {
            throw ValidationException::withMessages([
                'equipos' => ['Ambos equipos del partido deben existir para generar la cuota.'],
            ]);
        }

        $edicion = Edicion::query()->whereKey($idEdicion)->first(['id', 'nombre']);

        if ($edicion === null) {
            throw ValidationException::withMessages([
                'idEdicion' => ['La edición del partido no existe.'],
            ]);
        }

        $categoria = Categoria::query()->whereKey($idCategoria)->first(['id', 'idEdicion', 'nombreCategoria']);

        if ($categoria === null || (int) $categoria->idEdicion !== $idEdicion) {
            throw ValidationException::withMessages([
                'idCategoria' => ['La categoría del partido debe pertenecer a su edición.'],
            ]);
        }

        $inscripciones = EquipoEdicion::query()
            ->whereIn('idEquipo', [$idLocal, $idVisitante])
            ->where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->distinct()->pluck('idEquipo');

        if ($inscripciones->count() !== 2) {
            throw ValidationException::withMessages([
                'equipos' => ['Ambos equipos deben estar inscriptos en la edición y categoría del partido.'],
            ]);
        }

        return [
            'idEquipoLocal' => $idLocal,
            'idEquipoVisitante' => $idVisitante,
            'idEdicion' => $idEdicion,
            'idCategoria' => $idCategoria,
            'equipos' => $equipos->keyBy('id'),
            'edicion' => $edicion,
            'categoria' => $categoria,
        ];
    }

    /**
     * Alta del padre con veto: si no persiste con id real, aborta todo.
     *
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $contexto
     */
    private function crearPadre(array $datos, string $clasePadre, array $contexto): Partido|InstanciaFinal
    {
        $padre = $clasePadre === Partido::class ? new Partido : new InstanciaFinal;
        $padre->fill($this->atributosPadre($datos, $contexto, $clasePadre));

        if (! $padre->save() || ! $padre->exists) {
            throw new RuntimeException('No se pudo registrar el alta del partido.');
        }

        return $padre;
    }

    /**
     * Sólo campos conocidos del alta; el resto del payload se ignora.
     *
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $contexto
     * @return array<string, mixed>
     */
    private function atributosPadre(array $datos, array $contexto, string $clasePadre): array
    {
        if ($clasePadre === Partido::class) {
            $interzonal = $this->booleanoOpcional($datos, 'es_interzonal');

            return [
                'idFechas' => $this->enteroRequerido($datos, 'idFechas'),
                'idEquipoLocal' => $contexto['idEquipoLocal'],
                'idEquipoVisitante' => $contexto['idEquipoVisitante'],
                'idGrupo' => $interzonal ? null : $this->enteroRequerido($datos, 'idGrupo'),
                'idEdicion' => $contexto['idEdicion'],
                'idCategoria' => $contexto['idCategoria'],
                'horario_datetime' => $this->fechaHora($datos, 'horario_datetime', ! $interzonal),
                'cancha' => $interzonal ? $this->enteroOpcional($datos, 'cancha', 0) : $this->enteroRequerido($datos, 'cancha', 0),
                'jugado' => $this->enteroOpcional($datos, 'jugado', 0) ?? 0,
                'golesEquipoLocal' => $this->enteroOpcional($datos, 'golesEquipoLocal', 0),
                'golesEquipoVisitante' => $this->enteroOpcional($datos, 'golesEquipoVisitante', 0),
                'es_interzonal' => $interzonal,
            ];
        }

        return [
            'idCategoria' => $contexto['idCategoria'],
            'idCopa' => $this->enteroOpcional($datos, 'idCopa'),
            'idEdicion' => $contexto['idEdicion'],
            'idFase' => $this->enteroRequerido($datos, 'idFase'),
            'idEquipoLocal' => $contexto['idEquipoLocal'],
            'idEquipoVisitante' => $contexto['idEquipoVisitante'],
            'horario' => $this->fechaHora($datos, 'horario'),
            'cancha' => (string) $this->enteroRequerido($datos, 'cancha', 0),
        ];
    }

    private function enteroRequerido(array $datos, string $clave, int $minimo = 1): int
    {
        $valor = $datos[$clave] ?? null;
        $valido = is_int($valor) || (is_string($valor) && ctype_digit($valor));
        $numero = $valido ? filter_var($valor, FILTER_VALIDATE_INT) : false;

        if ($numero === false || $numero < $minimo || $numero > 2147483647) {
            throw ValidationException::withMessages([
                $clave => ["El campo {$clave} es obligatorio para la cuota de planilla."],
            ]);
        }

        return $numero;
    }

    private function enteroOpcional(array $datos, string $clave, int $minimo = 1): ?int
    {
        if (($datos[$clave] ?? null) === null) {
            return null;
        }

        return $this->enteroRequerido($datos, $clave, $minimo);
    }

    private function booleanoOpcional(array $datos, string $clave): bool
    {
        $valor = $datos[$clave] ?? false;

        if (! is_bool($valor)) {
            throw ValidationException::withMessages([
                $clave => ["El campo {$clave} debe ser verdadero o falso."],
            ]);
        }

        return $valor;
    }

    private function fechaHora(array $datos, string $clave, bool $requerida = true): ?string
    {
        $valor = $datos[$clave] ?? null;
        if (is_string($valor) && trim($valor) === '') {
            $valor = null;
        }
        Validator::make([$clave => $valor], [$clave => [$requerida ? 'required' : 'nullable', 'date']])->validate();

        return $valor === null ? null : Carbon::parse($valor)->format('Y-m-d H:i:s');
    }
}
