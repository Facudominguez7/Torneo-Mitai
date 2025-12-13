<?php

use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\ControladorHome;
use App\Http\Controllers\Panel\ControladorTablaGoleadores;
use App\Http\Controllers\Google\LoginController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\Panel\ControladorCampeon;
use App\Http\Controllers\Panel\ControladorCategoria;
use App\Http\Controllers\Panel\ControladorCopa;
use App\Http\Controllers\Panel\ControladorEdicion;
use App\Http\Controllers\Panel\ControladorEquipo;
use App\Http\Controllers\Panel\ControladorEquiposEdiciones;
use App\Http\Controllers\Panel\ControladorEquiposGrupos;
use App\Http\Controllers\Panel\ControladorFase;
use App\Http\Controllers\Panel\ControladorFecha;
use App\Http\Controllers\Panel\ControladorGoleador;
use App\Http\Controllers\Panel\ControladorGrupos;
use App\Http\Controllers\Panel\ControladorInstanciaFinal;
use App\Http\Controllers\Panel\ControladorPartidos;
use App\Http\Controllers\Panel\ControladorPlanillaJugador;
use App\Http\Controllers\Panel\ControladorSubcampeon;
use App\Http\Controllers\Panel\ControladorValla;
use App\Http\Controllers\Panel\ControladorConsultaJugador;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\UserAccesPanelMiddleware;
use App\Models\Categoria;

Route::get('/auth/redirect', [LoginController::class, 'redirect']);
Route::get('/auth/google/callback', [LoginController::class, 'callback']);
Route::get('Testloginfo', [LogController::class, 'Testloginfo'])->name('testloginfo');

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
Route::get('/tabla-de-posiciones', [ControladorHome::class, 'tablaPosiciones'])->name('tabla-posiciones');
Route::get('/tabla-de-goleadores', [ControladorHome::class, 'tablaGoleadores'])->name('tabla-goleadores');
Route::get('/tabla-de-vallas', [ControladorHome::class, 'tablaVallas'])->name('tabla-vallas');
Route::get('/instancias-finales', [ControladorHome::class, 'instanciasFinales'])->name('instancias-finales');
Route::get('forgot-password/{restablecer?}', [PasswordResetLinkController::class, 'create'])->name('password.request');



Route::group(['prefix' => 'Panel', 'middleware' => ['auth', 'verified', UserAccesPanelMiddleware::class]], function () {
    Route::resource('equipo', ControladorEquipo::class);
    Route::resource('categoria', ControladorCategoria::class);
    Route::resource('edicion', ControladorEdicion::class);
    Route::resource('campeon', ControladorCampeon::class);
    Route::resource('subcampeon', ControladorSubcampeon::class);
    Route::resource('copa', ControladorCopa::class);
    Route::resource('fecha', ControladorFecha::class);
    Route::resource('goleador', ControladorGoleador::class);
    Route::resource('valla', ControladorValla::class);
    Route::resource('grupos', ControladorGrupos::class);
    Route::resource('equipogrupo', ControladorEquiposGrupos::class);
    Route::resource('equipoedicion', ControladorEquiposEdiciones::class);
    Route::resource('partido', ControladorPartidos::class);
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
    Route::prefix('planilla')->group(function () {
        Route::get('/{partidoId}/{idEdicion}/{tipoPartido}/{horario?}', [ControladorPlanillaJugador::class, 'mostrarPlanilla'])->name('planilla.show');
        Route::post('/agregar-jugador', [ControladorPlanillaJugador::class, 'agregarJugador'])->name('planilla.agregarJugador');
        Route::post('/actualizar-jugador', [ControladorPlanillaJugador::class, 'actualizarJugadores'])->name('planilla.actualizarJugadores');
        Route::post('/eliminar-jugador', [ControladorPlanillaJugador::class, 'eliminarJugador'])->name('planilla.eliminarJugador');
    });
    
    // Lista de Buena Fe
    Route::prefix('lista-buena-fe')->group(function () {
        Route::get('/conflictivos', [\App\Http\Controllers\Panel\ControladorListaBuenaFe::class, 'conflictivos'])->name('lista-buena-fe.conflictivos');
        Route::get('/', [\App\Http\Controllers\Panel\ControladorListaBuenaFe::class, 'index'])->name('lista-buena-fe.index');
        Route::get('/{idEquipo}/{idEdicion}', [\App\Http\Controllers\Panel\ControladorListaBuenaFe::class, 'show'])->name('lista-buena-fe.show');
        Route::get('/pdf/{idEquipo}/{idEdicion}', [\App\Http\Controllers\Panel\ControladorListaBuenaFe::class, 'pdf'])->name('lista-buena-fe.pdf');
        Route::get('/imprimir-planilla/{idEquipo}/{idEdicion}', [\App\Http\Controllers\Panel\ControladorListaBuenaFe::class, 'imprimirPlanilla'])->name('lista-buena-fe.imprimir-planilla');
        Route::post('/agregar', [\App\Http\Controllers\Panel\ControladorListaBuenaFe::class, 'agregarJugador'])->name('lista-buena-fe.agregar');
        Route::put('/dar-baja/{id}', [\App\Http\Controllers\Panel\ControladorListaBuenaFe::class, 'darDeBaja'])->name('lista-buena-fe.dar-baja');
        Route::put('/reactivar/{id}', [\App\Http\Controllers\Panel\ControladorListaBuenaFe::class, 'reactivar'])->name('lista-buena-fe.reactivar');
        Route::put('/actualizar/{id}', [\App\Http\Controllers\Panel\ControladorListaBuenaFe::class, 'actualizar'])->name('lista-buena-fe.actualizar');
        Route::post('/migrar', [\App\Http\Controllers\Panel\ControladorListaBuenaFe::class, 'migrarDesdePartidos'])->name('lista-buena-fe.migrar');
        Route::post('/migrar-todos', [\App\Http\Controllers\Panel\ControladorListaBuenaFe::class, 'migrarTodos'])->name('lista-buena-fe.migrar-todos');
        Route::put('/resolver-conflicto/{id}', [\App\Http\Controllers\Panel\ControladorListaBuenaFe::class, 'resolverConflicto'])->name('lista-buena-fe.resolver-conflicto');
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
        Route::get('/', [\App\Http\Controllers\Panel\ControladorInstitucion::class, 'index'])->name('institucion.index');
        Route::post('/', [\App\Http\Controllers\Panel\ControladorInstitucion::class, 'store'])->name('institucion.store');
        Route::put('/{institucion}', [\App\Http\Controllers\Panel\ControladorInstitucion::class, 'update'])->name('institucion.update');
        Route::post('/cambiar-equipo', [\App\Http\Controllers\Panel\ControladorInstitucion::class, 'cambiarInstitucionEquipo'])->name('institucion.cambiarEquipo');
        Route::post('/desvincular-equipo', [\App\Http\Controllers\Panel\ControladorInstitucion::class, 'desvincularEquipo'])->name('institucion.desvincularEquipo');
    });

    // Estadísticas de Jugadores (vista global con estadísticas por edición)
    Route::get('/estadisticas-jugadores', [\App\Http\Controllers\Panel\ControladorEstadisticasJugadores::class, 'index'])->name('estadisticas-jugadores');
});

// Notificaciones Push - Solo requiere autenticación (no verified) para permitir usuarios Google
Route::prefix('Panel/push')->middleware(['auth'])->group(function () {
    Route::post('/subscribe', [\App\Http\Controllers\ControladorNotificacionesPush::class, 'subscribe'])
        ->middleware('throttle:10,1') // Rate limit: 10 requests por minuto
        ->name('push.subscribe');
    Route::post('/unsubscribe', [\App\Http\Controllers\ControladorNotificacionesPush::class, 'unsubscribe'])
        ->middleware('throttle:10,1') // Rate limit: 10 requests por minuto
        ->name('push.unsubscribe');
    Route::get('/status', [\App\Http\Controllers\ControladorNotificacionesPush::class, 'status'])
        ->name('push.status');
    Route::post('/enviar-manual', [\App\Http\Controllers\ControladorNotificacionesPush::class, 'enviarNotificacionManual'])
        ->middleware(UserAccesPanelMiddleware::class) // Solo admins
        ->name('push.enviar-manual');
    Route::get('/suscriptores', [\App\Http\Controllers\ControladorNotificacionesPush::class, 'listarSuscriptores'])
        ->middleware(UserAccesPanelMiddleware::class) // Solo admins
        ->name('push.suscriptores');
});

// Ruta pública para obtener la clave VAPID (no requiere autenticación)
Route::get('/push/vapid-public-key', [\App\Http\Controllers\ControladorNotificacionesPush::class, 'getVapidPublicKey'])
    ->name('push.vapid');



require __DIR__ . '/auth.php';

