<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cobros\ConsultaRequest;
use App\Http\Requests\Cobros\DeudaManualRequest;
use App\Http\Requests\Cobros\OperacionRequest;
use App\Models\AplicacionPagoPlanilla;
use App\Models\CargoPlanilla;
use App\Models\Categoria;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\CuentaCobroPlanilla;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\ExencionCobroPlanilla;
use App\Models\Fecha;
use App\Models\HistorialCobroPlanilla;
use App\Models\PagoPlanilla;
use App\Models\PoliticaExencionCobroPlanilla;
use App\Services\Cobros\AusenciasCobroService;
use App\Services\Cobros\ConfiguracionCobroService;
use App\Services\Cobros\ConsultaCobrosService;
use App\Services\Cobros\CreditosCobroService;
use App\Services\Cobros\DeudasManualesCobroService;
use App\Services\Cobros\ExencionesCobroService;
use App\Services\Cobros\PagosCobroService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ControladorCobros extends Controller
{
    public function index(ConsultaRequest $request, ConsultaCobrosService $consulta): View
    {
        $datos = $this->datos($request, $consulta);
        $filas = $datos['cargos'];
        $pagina = $request->integer('page', 1);
        $datos['cargos'] = new LengthAwarePaginator($filas->forPage($pagina, 25), $filas->count(), 25, $pagina, [
            'path' => $request->url(), 'query' => $request->query(),
        ]);
        $datos['deudores'] = $datos['EdicionSeleccionada'] ? $consulta->deudoresPorEquipo((int) $datos['EdicionSeleccionada']->id) : collect();

        return view('Panel.cobros.index', $datos);
    }

    public function imprimir(ConsultaRequest $request, ConsultaCobrosService $consulta): View
    {
        return view('Panel.cobros.imprimir', $this->datos($request, $consulta));
    }

    public function operar(OperacionRequest $request, ConsultaCobrosService $consulta): JsonResponse
    {
        try {
            $resultado = DB::transaction(function () use ($request, $consulta): array {
                $actor = $request->user();
                $cargo = $request->integer('cargo_id');
                $equipo = $request->integer('idEquipo');
                $edicion = $request->integer('idEdicion');
                switch ($request->route()->getName()) {
                    case 'cobros.pagos':
                        $partes = $request->partesPago();
                        if (count($partes) > 1 && ! PagoPlanilla::query()->where('clave_idempotencia', strtolower($partes[0]['clave']))->exists()) {
                            $deuda = $consulta->deudaCargo($cargo);
                            if ($partes[0]['importe_ars'] >= $deuda) {
                                throw ValidationException::withMessages(['importe_efectivo_ars' => ['Con dos medios, cada importe debe ser menor que la deuda del cargo ($ '.number_format($deuda, 0, ',', '.').'). Registrá el excedente en un solo medio.']]);
                            }
                        }
                        $pagos = [];
                        foreach ($partes as $parte) {
                            $pago = app(PagosCobroService::class)->registrarPago($actor, $cargo, $parte['importe_ars'], $parte['medio'], $parte['clave']);
                            if ($pago->wasRecentlyCreated && $pago->importe_ars > AplicacionPagoPlanilla::query()->where('pago_id', $pago->id)->sum('importe_ars')) {
                                $deudas = $consulta->consultar(['idEdicion' => $edicion, 'idEquipo' => $equipo, 'periodo' => 'todos', 'estado' => 'deudores'])['cargos'];
                                if ($deudas->isNotEmpty()) {
                                    app(CreditosCobroService::class)->aplicarCredito($actor, $pago->id, $cargo, $parte['clave']);
                                }
                            }
                            $pagos[] = $pago;
                        }
                        $resultados = array_map(fn (PagoPlanilla $pago): array => $consulta->resultadoPago($pago), $pagos);

                        return ['pago' => end($resultados), 'pagos' => $resultados];
                    case 'cobros.exenciones':
                        app(ExencionesCobroService::class)->eximirCargo($actor, $equipo, $cargo, $request->input('motivo'));
                        break;
                    case 'cobros.exenciones-edicion':
                        return ['exencion_edicion' => app(ExencionesCobroService::class)->eximirEdicion($actor, $equipo, $edicion, $request->input('motivo'))];
                    case 'cobros.no-vino':
                        app(AusenciasCobroService::class)->marcarNoVino($actor, $equipo, $cargo, $request->input('motivo'));
                        break;
                    case 'cobros.no-vino-deshacer':
                        app(AusenciasCobroService::class)->deshacerNoVino($actor, $equipo, $cargo);
                        break;
                    case 'cobros.politicas':
                        app(ExencionesCobroService::class)->registrarPolitica($actor, $equipo, $edicion, $request->input('motivo'));
                        break;
                    case 'cobros.tarifa':
                        app(ConfiguracionCobroService::class)->actualizarTarifa($actor, $request->integer('tarifa_ars'));
                        break;
                    case 'cobros.activar':
                        app(ConfiguracionCobroService::class)->activar($actor);
                        break;
                }

                return [];
            });

            return response()->json(['message' => 'Operación registrada.'] + $resultado);
        } catch (ValidationException|AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Falló una operación HTTP de cobros.', ['actor_id' => $request->user()->id, 'operacion' => $request->route()->getName(), 'excepcion' => $exception::class]);

            return response()->json(['message' => 'No se pudo completar la operación. Revisá e intentá nuevamente.'], 500);
        }
    }

    public function detalle(ConsultaRequest $request, ConsultaCobrosService $consulta): View
    {
        abort_unless($request->filled('idEquipo') && $request->filled('idEdicion'), 422);
        $equipo = $request->integer('idEquipo');
        $edicion = $request->integer('idEdicion');
        $datos = $consulta->consultar(['idEquipo' => $equipo, 'idEdicion' => $edicion, 'periodo' => 'todos', 'estado' => 'todos']);
        $cuentas = CuentaCobroPlanilla::query()->where('idEquipo', $equipo)->where('idEdicion', $edicion)->pluck('id');
        $pagos = PagoPlanilla::query()->whereIn('cuenta_id', $cuentas)->orderByDesc('id')->get();
        $cargos = $datos['cargos']->pluck('id');
        $eventos = HistorialCobroPlanilla::query()->where(function ($query) use ($equipo, $edicion, $cargos, $pagos): void {
            $query->where(function ($q) use ($equipo, $edicion): void {
                $q->where('valores_antes->idEquipo', $equipo)->where('valores_antes->idEdicion', $edicion);
            })->orWhereIn('valores_antes->cargo_id', $cargos)->orWhereIn('valores_antes->pago_id', $pagos->modelKeys());
        })->orderByDesc('id')->get();

        return view('Panel.cobros.detalle', $datos + [
            'pagos' => $pagos, 'eventos' => $eventos, 'idEquipo' => $equipo, 'idEdicion' => $edicion,
            'aplicaciones' => AplicacionPagoPlanilla::query()->whereIn('pago_id', $pagos->modelKeys())->get()->groupBy('pago_id'),
            'exenciones' => ExencionCobroPlanilla::query()->whereIn('cargo_id', $cargos)->get(),
            'politica' => PoliticaExencionCobroPlanilla::query()->where('idEquipo', $equipo)->where('idEdicion', $edicion)->first(),
        ]);
    }

    /** Estado de cobro de un partido para refrescar la planilla sin recargarla. */
    public function partido(ConsultaRequest $request, ConsultaCobrosService $consulta): View
    {
        abort_unless($request->filled('tipo') && $request->filled('partido'), 422);
        $tipo = (string) $request->input('tipo');
        $partido = CargoPlanilla::TIPOS_FINANCIEROS[$tipo]::query()->with(['equipoLocal', 'equipoVisitante'])->findOrFail($request->integer('partido'));

        return view('Panel.cobros.partido', [
            'partido' => $partido,
            'cobrosPlanilla' => $consulta->cargosDePartidos($tipo, [(int) $partido->id])->keyBy('idEquipo'),
            'configuracionCobros' => ConfiguracionCobroPlanilla::query()->find(1),
        ]);
    }

    /** Formulario para cargar a mano la deuda de un partido jugado sin cargo. */
    public function deudaManual(ConsultaRequest $request, DeudasManualesCobroService $deudas): View
    {
        $idEdicion = $request->validated('idEdicion');
        $idEquipo = $request->validated('idEquipo');
        $equipos = Equipo::query()->whereHas('ediciones', fn ($query) => $query->where('ediciones.id', $idEdicion))->orderBy('nombre')->get();
        $equipo = $equipos->firstWhere('id', $idEquipo);

        return view('Panel.cobros.deuda-manual', [
            'EdicionSeleccionada' => Edicion::query()->find($idEdicion),
            'equipos' => $equipos,
            'equipo' => $equipo,
            'partidos' => $equipo ? $deudas->partidosJugados((int) $idEdicion, (int) $equipo->id) : collect(),
            'tarifa' => ConfiguracionCobroPlanilla::query()->find(1)?->tarifa_ars,
        ]);
    }

    public function cargarDeudaManual(DeudaManualRequest $request, DeudasManualesCobroService $deudas): RedirectResponse
    {
        $datos = $request->validated();
        $cargo = $deudas->cargarDeuda(
            $request->user(), $datos['tipo'], (int) $datos['partido_id'], (int) $datos['idEquipo'],
            (int) $datos['importe_ars'], $datos['motivo'] ?? null, (int) $datos['idEdicion'],
        );

        return redirect()->route('cobros.deuda-manual', ['idEdicion' => $datos['idEdicion'], 'idEquipo' => $datos['idEquipo']])
            ->with('success', sprintf(
                'Deuda cargada: %s debe $ %s de la planilla del partido.',
                $cargo->snapshot['equipo']['nombre'] ?? 'El equipo', number_format($cargo->precio_original, 0, ',', '.'),
            ));
    }

    private function datos(ConsultaRequest $request, ConsultaCobrosService $consulta): array
    {
        $filtros = array_filter($request->validated(), fn (mixed $valor): bool => $valor !== null && $valor !== '');
        // La pantalla abre con todos los cargos: exentos, pagados, futuros y sin fecha incluidos.
        $filtros += ['estado' => 'todos', 'periodo' => 'todos'];
        $idEdicion = $filtros['idEdicion'] ?? null;

        return $consulta->consultar($filtros) + [
            'filtros' => $filtros,
            'EdicionSeleccionada' => Edicion::query()->find($idEdicion),
            'categorias' => Categoria::query()->where('idEdicion', $idEdicion)->orderBy('nombreCategoria')->get(),
            'equipos' => Equipo::query()->whereHas('ediciones', fn ($query) => $query->where('ediciones.id', $idEdicion))->orderBy('nombre')->get(),
            'fechas' => Fecha::query()->where('idEdicion', $idEdicion)->orderBy('id')->get(),
        ];
    }
}
