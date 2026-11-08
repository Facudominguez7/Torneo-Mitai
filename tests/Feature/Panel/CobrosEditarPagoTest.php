<?php

// Editar por HTTP un pago ya registrado desde la planilla del partido: el endpoint
// admin devuelve el evento `editar_pago` como comprobante y, por separado, el estado
// vigente releído del pago. La planilla ofrece una acción por pago editable, bloquea
// los pagos cuyo excedente ya cubrió otras planillas y refresca por polling.

use App\Models\AplicacionPagoPlanilla;
use App\Models\CargoPlanilla;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\PagoPlanilla;
use App\Models\Partido;
use App\Services\Cobros\ConsultaCobrosService;
use App\Services\Cobros\CreditosCobroService;
use App\Services\Cobros\PagosCobroService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require_once __DIR__.'/../Services/Cobros/FixturesCargos.php';

function cargoEditarPago(array $contexto, $equipo, string $horario = '2026-04-05 10:00:00', int $precio = 25000): CargoPlanilla
{
    $rival = crearEquipoCargos($contexto, 'Rival '.$horario);
    $partido = Partido::create(crearDatosPartidoCargos($contexto, $equipo, $rival, ['horario_datetime' => $horario]));

    return CargoPlanilla::create([
        'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $partido->id,
        'idEquipo' => $equipo->id, 'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id, 'precio_original' => $precio, 'snapshot' => [],
    ]);
}

function entradaEditarPago(CargoPlanilla $cargo, PagoPlanilla $pago, array $cambios = []): array
{
    return array_replace([
        'idEdicion' => $cargo->idEdicion, 'idEquipo' => $cargo->idEquipo, 'cargo_id' => $cargo->id,
        'pago_id' => $pago->id, 'importe_ars' => '4000', 'medio' => 'efectivo',
        'clave_idempotencia' => (string) Str::uuid(),
    ], $cambios);
}

/** Fotografía de las tablas financieras para probar que un rechazo no escribe nada. */
function fotoEditarPago(): array
{
    return collect(['pago_planillas', 'aplicacion_pago_planillas', 'historial_cobro_planillas'])
        ->mapWithKeys(fn (string $tabla): array => [$tabla => json_encode(DB::table($tabla)->orderBy('id')->get())])->all();
}

function urlPollingEditarPago(string $html): string
{
    preg_match('/<[^>]*data-cobros-seccion\b[^>]*>/s', $html, $seccion);
    preg_match('/data-url="([^"]+)"/', $seccion[0] ?? '', $url);

    expect($url[1] ?? null)->not->toBeNull();

    return html_entity_decode($url[1], ENT_QUOTES, 'UTF-8');
}

function firmaEditarPago(string $html): string
{
    preg_match('/data-firma="([^"]+)"/', $html, $firma);

    return $firma[1] ?? '';
}

beforeEach(function (): void {
    config(['logging.default' => 'stderr']);
    $this->contexto = crearContextoCargos('Edición editar pago');
    $this->equipo = crearEquipoCargos($this->contexto, 'Equipo editar pago');
    $this->cargo = cargoEditarPago($this->contexto, $this->equipo, '2026-04-05 10:00:00');
    $this->otroCargo = cargoEditarPago($this->contexto, $this->equipo, '2026-04-06 10:00:00');
    $this->admin = crearAdminCargos('Admin editar pago');
    activarCobrosCargos();
    $this->pago = (new PagosCobroService)->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', (string) Str::uuid());
});

it('baja el importe y responde el comprobante con el estado vigente releído', function (): void {
    $respuesta = $this->actingAs($this->admin)->postJson(route('cobros.pagos-editar'), entradaEditarPago(
        $this->cargo, $this->pago, ['importe_ars' => '4000', 'motivo' => "  Cobro mal cargado\n literal  "],
    ))->assertSuccessful()
        ->assertJsonPath('evento.accion', 'editar_pago')
        ->assertJsonPath('evento.pago_id', $this->pago->id)
        ->assertJsonPath('evento.importe_antes', 10000)->assertJsonPath('evento.medio_antes', 'efectivo')
        ->assertJsonPath('evento.importe_despues', 4000)->assertJsonPath('evento.medio_despues', 'efectivo')
        ->assertJsonPath('evento.motivo', "  Cobro mal cargado\n literal  ")
        ->assertJsonPath('pago.id', $this->pago->id)->assertJsonPath('pago.importe_ars', 4000)
        ->assertJsonPath('pago.medio', 'efectivo')->assertJsonPath('pago.aplicado_ars', 4000)
        ->assertJsonPath('pago.aplicado_origen_ars', 4000)->assertJsonPath('pago.deuda_cargo_ars', 21000)
        ->assertJsonPath('pago.credito_pago_ars', 0)->assertJsonPath('pago.credito_equipo_ars', 0);

    expect($respuesta->json('message'))->toBeString()->not->toBeEmpty()
        ->and((int) PagoPlanilla::query()->findOrFail($this->pago->id)->importe_ars)->toBe(4000)
        ->and((int) AplicacionPagoPlanilla::query()->sum('importe_ars'))->toBe(4000)
        ->and(app(ConsultaCobrosService::class)->deudaCargo($this->cargo->id))->toBe(21000);
    $evento = HistorialCobroPlanilla::query()->where('accion', 'editar_pago')->sole();
    expect([$evento->usuario_id, $evento->actor_nombre])->toBe([$this->admin->id, 'Admin editar pago']);
});

it('edita solo el medio y conserva importe y aplicación', function (): void {
    $this->actingAs($this->admin)->postJson(route('cobros.pagos-editar'), entradaEditarPago(
        $this->cargo, $this->pago, ['importe_ars' => '10000', 'medio' => 'mercado_pago'],
    ))->assertSuccessful()
        ->assertJsonPath('evento.importe_despues', 10000)->assertJsonPath('evento.medio_despues', 'mercado_pago')
        ->assertJsonPath('pago.medio', 'mercado_pago')->assertJsonPath('pago.aplicado_ars', 10000)
        ->assertJsonPath('pago.deuda_cargo_ars', 15000);

    expect(PagoPlanilla::query()->findOrFail($this->pago->id)->medio)->toBe('mercado_pago')
        ->and((int) AplicacionPagoPlanilla::query()->sum('importe_ars'))->toBe(10000)
        ->and(AplicacionPagoPlanilla::query()->count())->toBe(1);
});

it('sube el importe por encima de la deuda y reparte el excedente como crédito', function (): void {
    $this->actingAs($this->admin)->postJson(route('cobros.pagos-editar'), entradaEditarPago(
        $this->cargo, $this->pago, ['importe_ars' => '40000'],
    ))->assertSuccessful()
        ->assertJsonPath('pago.importe_ars', 40000)->assertJsonPath('pago.aplicado_origen_ars', 25000)
        ->assertJsonPath('pago.aplicado_ars', 40000)->assertJsonPath('pago.deuda_cargo_ars', 0)
        ->assertJsonPath('pago.credito_pago_ars', 0)->assertJsonPath('pago.credito_equipo_ars', 0);

    expect((int) PagoPlanilla::query()->findOrFail($this->pago->id)->importe_ars)->toBe(40000)
        ->and((int) AplicacionPagoPlanilla::query()->where('cargo_id', $this->cargo->id)->sum('importe_ars'))->toBe(25000)
        ->and((int) AplicacionPagoPlanilla::query()->where('cargo_id', $this->otroCargo->id)->sum('importe_ars'))->toBe(15000)
        ->and(app(ConsultaCobrosService::class)->deudaCargo($this->otroCargo->id))->toBe(10000);
});

it('rechaza importes medios y claves inválidas sin escribir ni auditar', function (array $cambios, string $campo): void {
    $foto = fotoEditarPago();

    $this->actingAs($this->admin)->postJson(route('cobros.pagos-editar'), entradaEditarPago($this->cargo, $this->pago, $cambios))
        ->assertUnprocessable()->assertJsonValidationErrors($campo);

    expect(fotoEditarPago())->toBe($foto);
})->with([
    'cero' => [['importe_ars' => '0'], 'importe_ars'],
    'decimal' => [['importe_ars' => '100.5'], 'importe_ars'],
    'desborde' => [['importe_ars' => '2147483648'], 'importe_ars'],
    'nulo' => [['importe_ars' => null], 'importe_ars'],
    'sin importe' => [['importe_ars' => ''], 'importe_ars'],
    'transferencia' => [['medio' => 'transferencia'], 'medio'],
    'sin medio' => [['medio' => null], 'medio'],
    'clave inválida' => [['clave_idempotencia' => 'no-es-uuid'], 'clave_idempotencia'],
]);

it('rechaza a invitados y a usuarios que no son administradores sin escribir', function (): void {
    $foto = fotoEditarPago();
    $this->postJson(route('cobros.pagos-editar'), entradaEditarPago($this->cargo, $this->pago))->assertNotFound();
    $this->actingAs(crearUsuarioConRolCargos('regular'))
        ->post(route('cobros.pagos-editar'), entradaEditarPago($this->cargo, $this->pago))->assertRedirect('/');
    $this->actingAs(crearUsuarioConRolCargos('editor'))
        ->postJson(route('cobros.pagos-editar'), entradaEditarPago($this->cargo, $this->pago))->assertRedirect('/');

    expect(fotoEditarPago())->toBe($foto);
});

it('rechaza un pago ajeno al cargo equipo y edición indicados sin escribir', function (): void {
    $ajeno = crearContextoCargos('Edición ajena editar pago');
    $equipoAjeno = crearEquipoCargos($ajeno, 'Equipo ajeno editar pago');
    $cargoAjeno = cargoEditarPago($ajeno, $equipoAjeno);
    $pagoAjeno = (new PagosCobroService)->registrarPago($this->admin, $cargoAjeno->id, 9000, 'efectivo', (string) Str::uuid());
    $foto = fotoEditarPago();
    $this->actingAs($this->admin);

    $this->postJson(route('cobros.pagos-editar'), entradaEditarPago($this->cargo, $pagoAjeno))->assertUnprocessable();
    $this->postJson(route('cobros.pagos-editar'), entradaEditarPago($this->otroCargo, $this->pago))->assertUnprocessable();
    $this->postJson(route('cobros.pagos-editar'), entradaEditarPago($this->cargo, $this->pago, ['idEquipo' => $equipoAjeno->id]))->assertUnprocessable();
    $this->postJson(route('cobros.pagos-editar'), entradaEditarPago($this->cargo, $this->pago, ['idEdicion' => $ajeno['edicion']->id]))->assertUnprocessable();

    expect(fotoEditarPago())->toBe($foto);
});

it('bloquea la edición cuando el excedente ya cubrió otras planillas', function (): void {
    $pago = (new PagosCobroService)->registrarPago($this->admin, $this->cargo->id, 20000, 'efectivo', (string) Str::uuid());
    (new CreditosCobroService)->aplicarCredito($this->admin, $pago->id, $this->cargo->id, (string) Str::uuid());
    $foto = fotoEditarPago();

    $this->actingAs($this->admin)->postJson(route('cobros.pagos-editar'), entradaEditarPago($this->cargo, $pago))
        ->assertUnprocessable()
        ->assertJsonPath('errors.pago.0', 'El pago ya distribuyó su excedente como crédito a otras planillas: la edición está bloqueada.');

    expect(fotoEditarPago())->toBe($foto);
});

it('reintenta la misma clave sin duplicar la edición y devuelve el estado vigente', function (): void {
    $this->actingAs($this->admin);
    $clave = (string) Str::uuid();
    $primera = entradaEditarPago($this->cargo, $this->pago, ['importe_ars' => '4000', 'clave_idempotencia' => $clave]);

    $this->postJson(route('cobros.pagos-editar'), $primera)->assertSuccessful()
        ->assertJsonPath('evento.importe_despues', 4000)->assertJsonPath('pago.importe_ars', 4000);
    $this->postJson(route('cobros.pagos-editar'), array_replace($primera, ['importe_ars' => '6000', 'clave_idempotencia' => (string) Str::uuid()]))
        ->assertSuccessful()->assertJsonPath('pago.importe_ars', 6000);

    // Repetir la primera operación devuelve su comprobante inmutable, no el estado actual.
    $this->postJson(route('cobros.pagos-editar'), $primera)->assertSuccessful()
        ->assertJsonPath('evento.importe_despues', 4000)->assertJsonPath('pago.importe_ars', 6000);
    // La misma clave con otros datos se rechaza sin efectos.
    $this->postJson(route('cobros.pagos-editar'), array_replace($primera, ['importe_ars' => '5000']))
        ->assertUnprocessable()->assertJsonValidationErrors('clave_idempotencia');

    expect(HistorialCobroPlanilla::query()->where('accion', 'editar_pago')->count())->toBe(2)
        ->and((int) PagoPlanilla::query()->findOrFail($this->pago->id)->importe_ars)->toBe(6000);
});

it('muestra cada pago con su edición en la planilla y deshabilita el del excedente repartido', function (): void {
    $contexto = crearContextoCargos('Edición planilla editar pago');
    $local = crearEquipoCargos($contexto, 'Local planilla editar');
    $visitante = crearEquipoCargos($contexto, 'Visitante planilla editar');
    $partido = Partido::create(crearDatosPartidoCargos($contexto, $local, $visitante, ['horario_datetime' => '2026-04-10 10:00:00']));
    $cargoLocal = CargoPlanilla::create([
        'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $partido->id,
        'idEquipo' => $local->id, 'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id, 'precio_original' => 25000, 'snapshot' => [],
    ]);
    $cargoVisitante = CargoPlanilla::create([
        'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $partido->id,
        'idEquipo' => $visitante->id, 'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id, 'precio_original' => 25000, 'snapshot' => [],
    ]);
    $cargoExtra = cargoEditarPago($contexto, $local, '2026-04-09 10:00:00');
    $pagoRepartido = (new PagosCobroService)->registrarPago($this->admin, $cargoLocal->id, 40000, 'efectivo', (string) Str::uuid());
    (new CreditosCobroService)->aplicarCredito($this->admin, $pagoRepartido->id, $cargoLocal->id, (string) Str::uuid());
    $pagoPagado = (new PagosCobroService)->registrarPago($this->admin, $cargoVisitante->id, 25000, 'mercado_pago', (string) Str::uuid());

    $url = route('planilla.show', ['partidoId' => $partido->id, 'idEdicion' => $contexto['edicion']->id, 'tipoPartido' => 'partido', 'horario' => 0]);
    $pagina = $this->actingAs($this->admin)->get($url)->assertOk();
    $html = $pagina->getContent();
    $pagina->assertSee('Editar pago')->assertSee('Pago #'.$pagoPagado->id)
        ->assertSee('No se puede editar: su excedente ya cubrió otras planillas.')
        ->assertSee('Pagada');
    expect(substr_count($html, 'data-cobro="editar_pago"'))->toBe(1)
        ->and($html)->toContain('data-pago-registrado="'.$pagoRepartido->id.'" data-editable="0"')
        ->and($html)->toContain('data-pago-registrado="'.$pagoPagado->id.'" data-editable="1"')
        ->and($html)->toContain('Pago #'.$pagoRepartido->id);

    $polling = urlPollingEditarPago($html);
    $antes = $this->get($polling)->assertOk();
    $antes->assertSee('Editar pago')->assertSee('Pago #'.$pagoRepartido->id)
        ->assertSee('No se puede editar: su excedente ya cubrió otras planillas.');
    $firmaAntes = firmaEditarPago($antes->getContent());
    expect($firmaAntes)->not->toBeEmpty();

    $this->postJson(route('cobros.pagos-editar'), entradaEditarPago($cargoVisitante, $pagoPagado, [
        'importe_ars' => '4000', 'medio' => 'mercado_pago',
    ]))->assertSuccessful();

    $despues = $this->get($polling)->assertOk();
    $despues->assertSee('Pago #'.$pagoPagado->id)->assertSee('$ 4.000');
    expect(firmaEditarPago($despues->getContent()))->toBeString()->not->toBeEmpty()->not->toBe($firmaAntes);
    // El editor no recibe acciones de edición de pagos.
    $this->actingAs(crearUsuarioConRolCargos('editor'))->get($polling)->assertOk()->assertDontSee('data-cobro="editar_pago"', false);
});

it('presenta el modal de edición con el resumen, los textos exactos y el recuadro Al guardar', function (): void {
    $contexto = crearContextoCargos('Edición modal editar pago');
    $local = crearEquipoCargos($contexto, 'Local modal editar');
    $visitante = crearEquipoCargos($contexto, 'Visitante modal editar');
    $partido = Partido::create(crearDatosPartidoCargos($contexto, $local, $visitante, ['horario_datetime' => '2026-04-11 10:00:00']));
    $cargo = CargoPlanilla::create([
        'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $partido->id,
        'idEquipo' => $local->id, 'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id, 'precio_original' => 25000, 'snapshot' => [],
    ]);
    (new PagosCobroService)->registrarPago($this->admin, $cargo->id, 25000, 'efectivo', (string) Str::uuid());

    $url = route('planilla.show', ['partidoId' => $partido->id, 'idEdicion' => $contexto['edicion']->id, 'tipoPartido' => 'partido', 'horario' => 0]);
    $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();
    $modal = Str::between($html, 'id="cobros-modal"', '</dialog>');
    $edicion = Str::between($modal, 'data-campo="editar_pago"', 'data-campo="motivo"');

    expect($edicion)->toContain('<dl id="cobros-editar-resumen"')
        ->toContain('grid-cols-1 gap-3 sm:grid-cols-2')
        ->toContain('Se corrige este mismo pago: no se anula ni se crea otro. Conserva la fecha de recepción y quién lo registró.')
        ->toContain('Importe en pesos')->not->toContain('Importe (ARS enteros)')
        ->toContain('Solo números, sin puntos ni decimales.')
        ->toContain('<legend class="text-sm font-medium">Medio de pago</legend>')
        ->not->toMatch('/type="radio"[^>]*\brounded\b/')
        ->toContain('Al guardar')->toContain('role="status"')->toContain('aria-live="polite"')
        ->not->toContain('No hay cambios para guardar.');
    expect($modal)->toContain('>Confirmar</button>');

    // El texto del recuadro, del botón de envío y del motivo lo resuelve cobros.js, que la planilla incrusta en la página.
    $this->actingAs($this->admin)->get($url)->assertOk()
        ->assertSee('Editar pago #', false)->assertSee('Guardar cambios', false)
        ->assertSee('Todavía no cambiaste nada. Modificá el importe o el medio para poder guardar.', false)
        ->assertSee('Solo cambia el medio de pago. La deuda de la planilla no cambia.', false)
        ->assertSee('La planilla va a quedar debiendo ', false)->assertSee('La planilla queda paga.', false)
        ->assertSee('Sobran ', false)->assertSee('Ingresá un importe entero mayor que cero.', false)
        ->assertSee('Si este partido se ganó 3–0 porque el rival no vino, ese resultado queda suspendido hasta que esta planilla vuelva a estar paga.', false)
        ->assertSee('Motivo (opcional)', false)->assertSee('Se guarda en el historial tal como lo escribas.', false)
        ->assertSee('Pago #', false)->assertSee('corregido:', false)
        ->assertSee('Registrado', false)->assertSee('Deuda actual de la planilla', false)
        ->assertDontSee('No hay cambios para guardar.', false)
        ->assertDontSee('data-editar-sin-cambios', false)
        ->assertDontSee('Importe (ARS enteros)', false);

    // El payload del botón lleva el importe que el pago aplica hoy a su propia planilla (A) para previsualizar la deuda.
    $boton = Str::between($html, 'data-cobro="editar_pago"', '>');
    expect(html_entity_decode($boton))->toContain('"aplicado_origen_ars":25000');
});

it('guarda sin motivo cuando el motivo llega vacío o solo con espacios', function (string $motivo): void {
    $this->actingAs($this->admin)->postJson(route('cobros.pagos-editar'), entradaEditarPago(
        $this->cargo, $this->pago, ['importe_ars' => '4000', 'motivo' => $motivo],
    ))->assertSuccessful()->assertJsonPath('evento.motivo', null);

    $evento = HistorialCobroPlanilla::query()->where('accion', 'editar_pago')->sole();
    expect($evento->valores_despues['motivo'])->toBeNull();
    $html = $this->get(route('cobros.detalle', ['idEdicion' => $this->cargo->idEdicion, 'idEquipo' => $this->equipo->id]))
        ->assertOk()->getContent();
    expect(Str::between($html, '<section aria-label="Historial auditable">', '</section>'))
        ->toContain('editado:')->not->toContain('Motivo:');
})->with(['sin texto' => '', 'solo espacios' => '   ']);

it('omite la línea de motivo en el detalle para un evento viejo guardado con motivo vacío', function (): void {
    HistorialCobroPlanilla::create([
        'accion' => 'editar_pago', 'usuario_id' => $this->admin->id, 'actor_nombre' => 'Admin editar pago',
        'valores_antes' => ['pago_id' => $this->pago->id, 'clave_idempotencia' => (string) Str::uuid(), 'importe_ars' => 10000, 'medio' => 'efectivo', 'aplicaciones' => []],
        'valores_despues' => ['pago_id' => $this->pago->id, 'importe_ars' => 4000, 'medio' => 'efectivo', 'aplicaciones' => [], 'motivo' => ''],
    ]);

    $html = $this->actingAs($this->admin)->get(route('cobros.detalle', ['idEdicion' => $this->cargo->idEdicion, 'idEquipo' => $this->equipo->id]))
        ->assertOk()->getContent();

    expect(Str::between($html, '<section aria-label="Historial auditable">', '</section>'))
        ->toContain('editado:')->not->toContain('Motivo:');
});

it('rechaza la edición sin cambios con el mensaje en el campo del importe y sin escribir', function (): void {
    $foto = fotoEditarPago();

    $this->actingAs($this->admin)->postJson(route('cobros.pagos-editar'), entradaEditarPago(
        $this->cargo, $this->pago, ['importe_ars' => '10000', 'medio' => 'efectivo'],
    ))->assertUnprocessable()->assertJsonPath('errors.importe_ars.0', 'No hay cambios para guardar.');

    expect(fotoEditarPago())->toBe($foto)
        ->and(HistorialCobroPlanilla::query()->where('accion', 'editar_pago')->count())->toBe(0);
});

it('oculta la acción de editar pago en todas las vistas cuando los cobros están desactivados', function (): void {
    ConfiguracionCobroPlanilla::query()->whereKey(1)->update(['activado_at' => null]);
    $this->actingAs($this->admin);
    $partido = Partido::query()->findOrFail($this->cargo->parent_id);
    $urls = [
        route('cobros.partido', ['tipo' => CargoPlanilla::TIPO_PARTIDO, 'partido' => $partido->id, 'idEdicion' => $this->contexto['edicion']->id]),
        route('planilla.deudas', ['partidoId' => $partido->id, 'idEdicion' => $this->contexto['edicion']->id, 'tipoPartido' => 'partido']),
        route('planilla.show', ['partidoId' => $partido->id, 'idEdicion' => $this->contexto['edicion']->id, 'tipoPartido' => 'partido', 'horario' => 0]),
    ];

    foreach ($urls as $url) {
        $this->get($url)->assertOk()
            ->assertDontSee('data-cobro="editar_pago"', false)->assertDontSee('>Editar pago</button>', false);
    }

    activarCobrosCargos();
    $this->get($urls[0])->assertOk()->assertSee('data-cobro="editar_pago"', false);
});

it('muestra el evento de la edición en el detalle del equipo con sus valores vigentes', function (): void {
    $this->actingAs($this->admin)->postJson(route('cobros.pagos-editar'), entradaEditarPago(
        $this->cargo, $this->pago, ['importe_ars' => '4000', 'motivo' => 'Cobro mal cargado'],
    ))->assertSuccessful();

    $this->get(route('cobros.detalle', ['idEdicion' => $this->cargo->idEdicion, 'idEquipo' => $this->equipo->id]))
        ->assertOk()
        ->assertSee('Pago #'.$this->pago->id.' editado: $ 10.000 Efectivo → $ 4.000 Efectivo')
        ->assertSee('Motivo: Cobro mal cargado')->assertSee('Admin editar pago')
        ->assertSee('Pago #'.$this->pago->id.' · ')->assertSee('$ 4.000');
});
