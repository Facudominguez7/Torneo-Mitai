<?php

use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\ControladorEquipoSeguido;
use App\Http\Controllers\ControladorHome;
use App\Http\Controllers\ControladorNotificacionesEmail;
use App\Http\Controllers\ControladorPlanillaPublica;
use App\Http\Controllers\Google\LoginController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\Panel\ControladorCampeon;
use App\Http\Controllers\Panel\ControladorCategoria;
use App\Http\Controllers\Panel\ControladorCobros;
use App\Http\Controllers\Panel\ControladorConsultaJugador;
use App\Http\Controllers\Panel\ControladorCopa;
use App\Http\Controllers\Panel\ControladorEdicion;
use App\Http\Controllers\Panel\ControladorEquipo;
use App\Http\Controllers\Panel\ControladorEquiposEdiciones;
use App\Http\Controllers\Panel\ControladorEquiposGrupos;
use App\Http\Controllers\Panel\ControladorEstadisticasJugadores;
use App\Http\Controllers\Panel\ControladorFase;
use App\Http\Controllers\Panel\ControladorFecha;
use App\Http\Controllers\Panel\ControladorGoleador;
use App\Http\Controllers\Panel\ControladorGrupos;
use App\Http\Controllers\Panel\ControladorImpresiones;
use App\Http\Controllers\Panel\ControladorInstanciaFinal;
use App\Http\Controllers\Panel\ControladorInstitucion;
use App\Http\Controllers\Panel\ControladorListaBuenaFe;
use App\Http\Controllers\Panel\ControladorPartidos;
use App\Http\Controllers\Panel\ControladorPlanillaJugador;
use App\Http\Controllers\Panel\ControladorPodio;
use App\Http\Controllers\Panel\ControladorPremios;
use App\Http\Controllers\Panel\ControladorReemplazoEquipo;
use App\Http\Controllers\Panel\ControladorSubcampeon;
use App\Http\Controllers\Panel\ControladorTablaGoleadores;
use App\Http\Controllers\Panel\ControladorTablaPosiciones;
use App\Http\Controllers\Panel\ControladorValla;
use App\Http\Controllers\Panel\ControladorVallasRanking;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\PanelEdicionContextMiddleware;
use App\Http\Middleware\UserAccesPanelMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/auth/redirect', [LoginController::class, 'redirect']);
Route::get('/auth/google/callback', [LoginController::class, 'callback']);

if (app()->environment('local')) {
    Route::get('Testloginfo', [LogController::class, 'Testloginfo'])->name('testloginfo');
}

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/', [ControladorHome::class, 'index'])->name('home');
Route::get('/campeones', [ControladorHome::class, 'campeones'])->name('campeones');
Route::get('/subcampeones', [ControladorHome::class, 'subcampeones'])->name('subcampeones');
Route::get('/goleadores', [ControladorHome::class, 'goleadores'])->name('goleadores');
Route::get('/vallas', [ControladorHome::class, 'vallas'])->name('vallas');
Route::get('/fixture', [ControladorHome::class, 'fixture'])->name('fixture');
Route::get('/planillas-publicas/{tipoPartido}/{publicToken}', [ControladorPlanillaPublica::class, 'show'])
    ->name('planillas-publicas.show');
Route::get('/equipo-seguido', [ControladorEquipoSeguido::class, 'show'])->name('equipo-seguido');
Route::get('/tabla-de-posiciones', [ControladorHome::class, 'tablaPosiciones'])->name('tabla-posiciones');
Route::get('/tabla-de-goleadores', [ControladorHome::class, 'tablaGoleadores'])->name('tabla-goleadores');
Route::get('/tabla-de-vallas', [ControladorHome::class, 'tablaVallas'])->name('tabla-vallas');
Route::get('/instancias-finales', [ControladorHome::class, 'instanciasFinales'])->name('instancias-finales');
Route::get('forgot-password/{restablecer?}', [PasswordResetLinkController::class, 'create'])->name('password.request');

Route::group(['prefix' => 'Panel', 'middleware' => ['auth', 'verified', UserAccesPanelMiddleware::class, PanelEdicionContextMiddleware::class]], function () {
    Route::get('cobros', [ControladorCobros::class, 'index'])->name('cobros.index');
    Route::get('cobros/imprimir', [ControladorCobros::class, 'imprimir'])->name('cobros.imprimir');
    Route::get('cobros/detalle', [ControladorCobros::class, 'detalle'])->name('cobros.detalle');
    Route::get('cobros/partido', [ControladorCobros::class, 'partido'])->name('cobros.partido');
    foreach (['pagos', 'exenciones', 'exenciones-edicion', 'politicas', 'tarifa', 'activar'] as $operacion) {
        Route::post('cobros/'.$operacion, [ControladorCobros::class, 'operar'])->name('cobros.'.$operacion);
    }
    Route::resource('equipo', ControladorEquipo::class);
    Route::resource('categoria', ControladorCategoria::class);
    Route::resource('edicion', ControladorEdicion::class);
    Route::resource('campeon', ControladorCampeon::class);
    Route::resource('subcampeon', ControladorSubcampeon::class);
    Route::get('podio', [ControladorPodio::class, 'index'])->name('podio.index');
    Route::post('podio/confirmar', [ControladorPodio::class, 'confirmar'])->name('podio.confirmar');
    Route::get('impresiones', [ControladorImpresiones::class, 'index'])->name('impresiones.index');
    Route::resource('copa', ControladorCopa::class);
    Route::resource('fecha', ControladorFecha::class);
    Route::resource('goleador', ControladorGoleador::class);
    Route::resource('valla', ControladorValla::class);
    Route::get('premios', [ControladorPremios::class, 'index'])->name('premios.index');
    Route::post('premios/goleador', [ControladorPremios::class, 'guardarGoleador'])->name('premios.goleador');
    Route::post('premios/valla', [ControladorPremios::class, 'guardarValla'])->name('premios.valla');
    Route::get('vallas', [ControladorVallasRanking::class, 'index'])->name('vallas.index');
    Route::resource('tabla_posicion', ControladorTablaPosiciones::class);
    Route::get('reemplazo-equipo/crear', [ControladorReemplazoEquipo::class, 'create'])->name('reemplazo-equipo.create');
    Route::get('reemplazo-equipo/buscar-otra-edicion', [ControladorReemplazoEquipo::class, 'buscarOtraEdicion'])->name('reemplazo-equipo.buscar-otra-edicion');
    Route::post('reemplazo-equipo', [ControladorReemplazoEquipo::class, 'store'])->name('reemplazo-equipo.store');
    Route::resource('grupos', ControladorGrupos::class);
    Route::resource('equipogrupo', ControladorEquiposGrupos::class);
    Route::post('equipogrupo/asignar-pendientes', [ControladorEquiposGrupos::class, 'asignarPendientes'])
        ->name('equipogrupo.asignar-pendientes');
    Route::resource('equipoedicion', ControladorEquiposEdiciones::class);
    Route::resource('partido', ControladorPartidos::class);
    Route::get('partido-interzonal/create', [ControladorPartidos::class, 'createInterzonal'])->name('partido.create-interzonal');
    Route::post('partido-interzonal/store', [ControladorPartidos::class, 'storeInterzonal'])->name('partido.store-interzonal');
    Route::resource('tabla_goleador', ControladorTablaGoleadores::class);
    Route::resource('instancia_final', ControladorInstanciaFinal::class);
    Route::get('/instancia_final/{idEdicion}/{horario?}', [ControladorInstanciaFinal::class, 'index'])->name('instancia_final');
    Route::resource('fase', ControladorFase::class);
    Route::match(['get', 'post'], 'cargar-resultado', [ControladorPartidos::class, 'cargarResultado'])->name('cargar-resultado');
    Route::match(['get', 'post'], 'cargar-resultado-instancia', [ControladorInstanciaFinal::class, 'cargarResultadoInstancia'])->name('cargar-resultado-instancia');
    Route::get('seleccionar-categoria', [ControladorCampeon::class, 'seleccionarCategoria'])->name('seleccionar-categoria');
    Route::get('seleccionar-edicion', [ControladorEquiposEdiciones::class, 'seleccionarEdicion'])->name('seleccionar-edicion');
    // Búsqueda global de equipos por nombre (prioridad por idEdicion DESC)
    Route::get('buscar-equipos', [ControladorEquiposEdiciones::class, 'buscarEquipos'])->name('equipoedicion.buscar');
    Route::get('/admin', [ControladorHome::class, 'admin'])
        ->name('admin');
    Route::get('/admin/imprimir-tabla-posiciones', [ControladorHome::class, 'imprimirTablaPosiciones'])
        ->name('admin.imprimir-tabla-posiciones');
    Route::get('/admin/imprimir-fixture', [ControladorHome::class, 'imprimirFixture'])
        ->name('admin.imprimir-fixture');
    Route::get('/admin/imprimir-fixture-instancias-finales', [ControladorHome::class, 'imprimirFixtureInstanciasFinales'])
        ->name('admin.imprimir-fixture-instancias-finales');
    Route::get('/admin/imprimir-fixture-arbitros', [ControladorHome::class, 'imprimirFixtureArbitros'])
        ->name('admin.imprimir-fixture-arbitros');
    Route::get('/admin/imprimir-fixture-arbitros-partidos', [ControladorHome::class, 'imprimirFixtureArbitrosPartidos'])
        ->name('admin.imprimir-fixture-arbitros-partidos');
    Route::get('/admin/grupos-por-categoria/{idCategoria}', [ControladorHome::class, 'gruposPorCategoria'])
        ->name('admin.grupos-por-categoria');
    Route::get('/admin/fechas-por-categoria/{idCategoria}', [ControladorHome::class, 'fechasPorCategoria'])
        ->name('admin.fechas-por-categoria');
    Route::prefix('planilla')->group(function () {
        Route::get('/{partidoId}/{idEdicion}/{tipoPartido}/{horario?}', [ControladorPlanillaJugador::class, 'mostrarPlanilla'])->name('planilla.show');
        Route::post('/agregar-jugador', [ControladorPlanillaJugador::class, 'agregarJugador'])->name('planilla.agregarJugador');
        Route::post('/actualizar-jugador', [ControladorPlanillaJugador::class, 'actualizarJugadores'])->name('planilla.actualizarJugadores');
        Route::post('/copiar-partido-anterior', [ControladorPlanillaJugador::class, 'copiarPlanillaPartidoAnterior'])->name('planilla.copiarPartidoAnterior');
        Route::post('/actualizar-goles-arbitros', [ControladorPlanillaJugador::class, 'actualizarGolesArbitros'])->name('planilla.actualizarGolesArbitros');
        Route::post('/aplicar-goles-arbitrales', [ControladorPlanillaJugador::class, 'aplicarGolesArbitrales'])->name('planilla.aplicarGolesArbitrales');
        Route::post('/confirmar-goles', [ControladorPlanillaJugador::class, 'confirmarGoles'])->name('planilla.confirmarGoles');
        Route::post('/quitar-confirmacion-goles', [ControladorPlanillaJugador::class, 'quitarConfirmacionGoles'])->name('planilla.quitarConfirmacionGoles');
        Route::post('/eliminar-jugador', [ControladorPlanillaJugador::class, 'eliminarJugador'])->name('planilla.eliminarJugador');
    });

    // Lista de Buena Fe
    Route::prefix('lista-buena-fe')->group(function () {
        Route::get('/conflictivos', [ControladorListaBuenaFe::class, 'conflictivos'])->name('lista-buena-fe.conflictivos');
        Route::get('/', [ControladorListaBuenaFe::class, 'index'])->name('lista-buena-fe.index');
        Route::get('/imprimir-planillas/{idEdicion}', [ControladorListaBuenaFe::class, 'imprimirPlanillas'])->name('lista-buena-fe.imprimir-planillas');
        Route::get('/imprimir-planillas-fecha/{idEdicion}/{idFecha}', [ControladorListaBuenaFe::class, 'imprimirPlanillasPorFecha'])
            ->whereNumber(['idEdicion', 'idFecha'])
            ->name('lista-buena-fe.imprimir-planillas-fecha');
        Route::get('/{idEquipo}/{idEdicion}', [ControladorListaBuenaFe::class, 'show'])->name('lista-buena-fe.show');
        Route::get('/pdf/{idEquipo}/{idEdicion}', [ControladorListaBuenaFe::class, 'pdf'])->name('lista-buena-fe.pdf');
        Route::get('/imprimir-planilla/{idEquipo}/{idEdicion}', [ControladorListaBuenaFe::class, 'imprimirPlanilla'])->name('lista-buena-fe.imprimir-planilla');
        Route::post('/agregar', [ControladorListaBuenaFe::class, 'agregarJugador'])->name('lista-buena-fe.agregar');
        Route::put('/dar-baja/{id}', [ControladorListaBuenaFe::class, 'darDeBaja'])->name('lista-buena-fe.dar-baja');
        Route::put('/reactivar/{id}', [ControladorListaBuenaFe::class, 'reactivar'])->name('lista-buena-fe.reactivar');
        Route::put('/actualizar/{id}', [ControladorListaBuenaFe::class, 'actualizar'])->name('lista-buena-fe.actualizar');
        Route::post('/migrar', [ControladorListaBuenaFe::class, 'migrarDesdePartidos'])->name('lista-buena-fe.migrar');
        Route::post('/migrar-todos', [ControladorListaBuenaFe::class, 'migrarTodos'])->name('lista-buena-fe.migrar-todos');
        Route::put('/resolver-conflicto/{id}', [ControladorListaBuenaFe::class, 'resolverConflicto'])->name('lista-buena-fe.resolver-conflicto');
        Route::get('/buscar-dni', [ControladorListaBuenaFe::class, 'buscarPorDni'])->name('lista-buena-fe.buscar-dni');
    });

    // En routes/web.php
    Route::get('/categorias-por-edicion/{id}', [ControladorEdicion::class, 'categoriasPorEdicion'])->name('categorias.por.edicion');
    Route::get('/equipos-por-categoria/{id}', [ControladorEdicion::class, 'equiposPorCategoria']);
    Route::get('/reporte-jugadores/{equipoId}/{idEdicion}/{partidoId}', [ControladorPlanillaJugador::class, 'generarReportePDF'])->name('reporte.jugadores');

    // Consulta de jugador
    Route::get('/consulta-jugador', [ControladorConsultaJugador::class, 'index'])->name('jugador.consulta');
    Route::post('/consulta-jugador/buscar', [ControladorConsultaJugador::class, 'buscar'])->name('jugador.buscar');

    // Gestión de Instituciones
    Route::prefix('instituciones')->group(function () {
        Route::get('/', [ControladorInstitucion::class, 'index'])->name('institucion.index');
        Route::post('/', [ControladorInstitucion::class, 'store'])->name('institucion.store');
        Route::put('/{institucion}', [ControladorInstitucion::class, 'update'])->name('institucion.update');
        Route::post('/cambiar-equipo', [ControladorInstitucion::class, 'cambiarInstitucionEquipo'])->name('institucion.cambiarEquipo');
        Route::post('/desvincular-equipo', [ControladorInstitucion::class, 'desvincularEquipo'])->name('institucion.desvincularEquipo');
    });

    // Estadísticas de Jugadores (vista global con estadísticas por edición)
    Route::get('/estadisticas-jugadores', [ControladorEstadisticasJugadores::class, 'index'])->name('estadisticas-jugadores');
});

// Gestión administrativa de notificaciones por email
Route::prefix('Panel/notificaciones')->middleware(['auth', UserAccesPanelMiddleware::class])->group(function () {
    Route::get('/', [ControladorNotificacionesEmail::class, 'listarUsuariosNotificaciones'])
        ->name('notificaciones.index');
    Route::post('/suscriptores/{id}/reenviar-verificacion', [ControladorNotificacionesEmail::class, 'reenviarVerificacionManual'])
        ->name('notificaciones.reenviar-verificacion');
    Route::get('/recordatorios/contar', [ControladorNotificacionesEmail::class, 'contarUsuariosPendientes'])
        ->name('notificaciones.contar-pendientes');
    Route::post('/recordatorios/enviar-lote', [ControladorNotificacionesEmail::class, 'enviarLoteRecordatorios'])
        ->name('notificaciones.enviar-lote');
    Route::post('/recordatorios/log-completo', [ControladorNotificacionesEmail::class, 'logEnvioCompleto'])
        ->name('notificaciones.log-completo');
    Route::get('/manual/contar', [ControladorNotificacionesEmail::class, 'contarSuscriptores'])
        ->name('notificaciones.manual.contar');
    Route::post('/manual/enviar-lote', [ControladorNotificacionesEmail::class, 'enviarLoteNotificacionManual'])
        ->name('notificaciones.manual.enviar-lote');
    Route::get('/resumen-fecha/contar', [ControladorNotificacionesEmail::class, 'contarDestinatariosResumenFecha'])
        ->name('notificaciones.resumen-fecha.contar');
    Route::post('/resumen-fecha/enviar-lote', [ControladorNotificacionesEmail::class, 'enviarLoteResumenFecha'])
        ->name('notificaciones.resumen-fecha.enviar-lote');
    Route::post('/resultado/enviar-lote', [ControladorNotificacionesEmail::class, 'enviarLoteResultado'])
        ->name('notificaciones.resultado.enviar-lote');
    Route::post('/resultado-instancia/enviar-lote', [ControladorNotificacionesEmail::class, 'enviarLoteResultadoInstancia'])
        ->name('notificaciones.resultado-instancia.enviar-lote');
});

// Rutas para gestión de suscripción a emails de notificación
// Toggle de suscripción (requiere autenticación)
Route::post('/email/toggle-suscripcion', [ControladorNotificacionesEmail::class, 'toggleEmailSubscription'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('email.toggle');

// Darse de baja de emails sin login (usando token de verificación via query string)
Route::get('/email/baja', [ControladorNotificacionesEmail::class, 'darseDeBajaEmail'])
    ->middleware('throttle:5,1')
    ->name('email.baja');

// One-click unsubscribe via POST (RFC 8058 - requerido por Gmail/Outlook)
Route::post('/email/baja', [ControladorNotificacionesEmail::class, 'oneClickUnsubscribe'])
    ->middleware('throttle:10,1')
    ->name('email.baja.post');

// Baja general para envíos masivos en BCC
Route::get('/email/baja-general', [ControladorNotificacionesEmail::class, 'formularioBajaGeneral'])
    ->middleware('throttle:20,1')
    ->name('email.baja-general.form');
Route::post('/email/baja-general', [ControladorNotificacionesEmail::class, 'procesarBajaGeneral'])
    ->middleware('throttle:20,1')
    ->name('email.baja-general.procesar');

// Estado de suscripción a emails (requiere autenticación)
Route::get('/email/estado', [ControladorNotificacionesEmail::class, 'estadoEmail'])
    ->middleware('auth')
    ->name('email.estado');

require __DIR__.'/auth.php';
