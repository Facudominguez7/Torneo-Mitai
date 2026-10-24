<?php

namespace App\Services\Cobros;

use App\Models\CargoPlanilla;
use App\Models\Categoria;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\ExencionCobroPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\PoliticaExencionCobroPlanilla;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Motor de altas con cargos de planilla: crea el partido (regular,
 * interzonal o instancia final) y, con los cobros activados, genera sus
 * dos cuotas atómicas dentro de una única transacción.
 *
 * Los cargos sólo nacen junto a un alta nueva: no existe una vía para
 * generar cargos de partidos anteriores. Las altas HTTP proveen el actor
 * autenticado, que el motor vuelve a autorizar dentro de la transacción.
 */
class ProgramacionCobroService
{
    private const ID_GLOBAL = 1;

    private const TARIFA_MAXIMA = 2147483647;

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
     * Transacción raíz: actor, tarifa citada, validaciones, alta del padre,
     * dos cuotas y un solo evento de auditoría; cualquier fallo revierte todo.
     *
     * @param  array<string, mixed>  $datos
     */
    private function crearAltasConCargos(User $actor, array $datos, string $clasePadre): Partido|InstanciaFinal
    {
        return DB::transaction(function () use ($actor, $datos, $clasePadre): Partido|InstanciaFinal {
            // Orden de bloqueos: primero el actor, después la configuración,
            // ambos antes de escribir el padre.
            $actorVigente = $this->resolverActorAdmin($actor);
            $configuracion = $this->obtenerConfiguracionBloqueada();
            $contexto = $this->validarContextoMonetario($datos);

            $padre = $this->crearPadre($datos, $clasePadre, $contexto);

            if ($configuracion->activado_at === null) {
                return $padre; // Cobros inactivos: alta válida, cero cargos, cero auditoría.
            }

            $cargos = $this->generarCargos($padre, $contexto, $this->tarifaVigente($configuracion));
            $this->materializarPoliticas($actorVigente, $cargos);
            $this->registrarEventoCargos($actorVigente, $padre, $cargos);

            return $padre;
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
     * Precio citado una sola vez por alta: ambos cargos comparten tarifa,
     * y los cambios posteriores de configuración sólo afectan cargos futuros.
     */
    private function tarifaVigente(ConfiguracionCobroPlanilla $configuracion): int
    {
        $tarifa = $configuracion->tarifa_ars;

        if (! is_int($tarifa) || $tarifa < 1 || $tarifa > self::TARIFA_MAXIMA) {
            throw new RuntimeException('La tarifa configurada no es un importe válido en ARS.');
        }

        return $tarifa;
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
                'cancha' => $this->canchaPartido($datos, $interzonal),
                'jugado' => $this->enteroFormulario($datos, 'jugado', false) ?? 0,
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
            'cancha' => (string) $this->enteroFormulario($datos, 'cancha', true),
        ];
    }

    /**
     * Cuota de cada equipo con la misma tarifa citada; el snapshot congela
     * los nombres e identidad de la época para sobrevivir a borrados.
     *
     * @param  array<string, mixed>  $contexto
     * @return array<int, CargoPlanilla>
     */
    private function generarCargos(Partido|InstanciaFinal $padre, array $contexto, int $tarifa): array
    {
        $tipo = array_search($padre::class, CargoPlanilla::TIPOS_FINANCIEROS, true);

        if ($tipo === false) {
            throw new RuntimeException('El tipo de padre no pertenece a la whitelist financiera.');
        }

        $cargos = [];

        foreach ([$contexto['idEquipoLocal'], $contexto['idEquipoVisitante']] as $idEquipo) {
            $cargo = CargoPlanilla::query()->make([
                'tipo' => $tipo,
                'idEquipo' => $idEquipo,
                'idEdicion' => $contexto['idEdicion'],
                'idCategoria' => $contexto['idCategoria'],
                'precio_original' => $tarifa,
                'snapshot' => $this->construirSnapshot($padre, $contexto, $idEquipo),
            ]);
            $cargo->parent()->associate($padre);

            if (! $cargo->save() || ! $cargo->exists) {
                throw new RuntimeException('No se pudo registrar el cargo de planilla.');
            }

            $cargos[] = $cargo;
        }

        return $cargos;
    }

    /**
     * Exime sólo cargos recién creados; el otorgante histórico no se reautoriza.
     *
     * @param  array<int, CargoPlanilla>  $cargos
     */
    private function materializarPoliticas(User $actorVigente, array $cargos): void
    {
        $politicas = PoliticaExencionCobroPlanilla::query()
            ->where('idEdicion', $cargos[0]->idEdicion)
            ->whereIn('idEquipo', array_map(fn (CargoPlanilla $cargo): int => (int) $cargo->idEquipo, $cargos))
            ->orderBy('id')->lockForUpdate()->get()->keyBy('idEquipo');

        foreach ($cargos as $cargo) {
            $politica = $politicas->get($cargo->idEquipo);
            if ($politica === null) {
                continue;
            }
            $exencion = new ExencionCobroPlanilla([
                'cargo_id' => (int) $cargo->id, 'usuario_id' => (int) $actorVigente->id,
                'importe_ars' => $cargo->precio_original, 'motivo' => $politica->motivo,
                'politica_id' => (int) $politica->id,
            ]);
            if (! $exencion->save() || ! $exencion->exists) {
                throw new RuntimeException('No se pudo materializar la política de exención del cargo.');
            }
        }
    }

    /**
     * Snapshot por equipo: el cobrado como equipo, el otro como oponente.
     * La fecha se toma tal cual del alta; nunca se inventa si es nula.
     *
     * @param  array<string, mixed>  $contexto
     * @return array<string, mixed>
     */
    private function construirSnapshot(Partido|InstanciaFinal $padre, array $contexto, int $idEquipo): array
    {
        $esFinal = $padre instanceof InstanciaFinal;
        $idOponente = $idEquipo === $contexto['idEquipoLocal']
            ? $contexto['idEquipoVisitante']
            : $contexto['idEquipoLocal'];

        return [
            'equipo' => ['id' => $idEquipo, 'nombre' => $contexto['equipos'][$idEquipo]->nombre],
            'oponente' => ['id' => $idOponente, 'nombre' => $contexto['equipos'][$idOponente]->nombre],
            'edicion' => ['id' => $contexto['edicion']->id, 'nombre' => $contexto['edicion']->nombre],
            'categoria' => ['id' => $contexto['categoria']->id, 'nombre' => $contexto['categoria']->nombreCategoria],
            'cancha' => $padre->cancha,
            'fecha_partido' => $esFinal ? $padre->horario : $padre->horario_datetime,
            'partido' => [
                'id' => (int) $padre->getKey(),
                'public_token' => $padre->public_token,
                'es_interzonal' => $esFinal ? null : (bool) $padre->es_interzonal,
                'id_fecha' => $esFinal ? null : (int) $padre->idFechas,
            ],
        ];
    }

    /**
     * Un solo evento generar_cargos por padre, dentro de la transacción raíz.
     *
     * @param  array<int, CargoPlanilla>  $cargos
     */
    private function registrarEventoCargos(User $actorVigente, Partido|InstanciaFinal $padre, array $cargos): void
    {
        $evento = HistorialCobroPlanilla::query()->create([
            'accion' => 'generar_cargos',
            'usuario_id' => $actorVigente->getKey(),
            'actor_nombre' => $actorVigente->name,
            'valores_antes' => [],
            'valores_despues' => [
                'parent' => ['tipo' => $padre->getMorphClass(), 'clase' => $padre::class, 'id' => (int) $padre->getKey()],
                'cargos' => array_map(fn (CargoPlanilla $cargo): int => $cargo->id, $cargos),
                'equipos' => array_map(fn (CargoPlanilla $cargo): int => (int) $cargo->idEquipo, $cargos),
                'precio_ars' => $cargos[0]->precio_original,
                'fecha_partido' => $cargos[0]->snapshot['fecha_partido'],
            ],
        ]);

        if (! $evento->exists) {
            throw new RuntimeException('No se pudo registrar el historial de cobros.');
        }
    }

    /**
     * Conserva la cancha legacy sin coerción; la columna mantiene su contrato
     * de persistencia y cualquier rechazo revierte también los cargos.
     */
    private function canchaPartido(array $datos, bool $opcional): string|int|null
    {
        $valor = $datos['cancha'] ?? null;
        if (is_int($valor)) {
            return $valor;
        }

        Validator::make(['cancha' => $valor], ['cancha' => [$opcional ? 'nullable' : 'required', 'string']])->validate();

        return $valor;
    }

    /** Enteros de formulario no monetarios: admite también el signo legacy. */
    private function enteroFormulario(array $datos, string $clave, bool $requerido): int|string|null
    {
        $valor = $datos[$clave] ?? null;
        Validator::make([$clave => $valor], [$clave => [$requerido ? 'required' : 'nullable', 'integer']])->validate();

        return $valor;
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
