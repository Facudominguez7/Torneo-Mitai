<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Suscripcion\AvisoTorneoRequest;
use App\Http\Requests\Suscripcion\ResumenFechaRequest;
use App\Models\EnvioSuscriptores;
use App\Services\EnvioSuscriptoresService;
use App\Services\ResumenFechaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avisos masivos del panel a los suscriptores: cuenta los destinatarios y dispara el envío.
 */
class ControladorEnvioSuscriptores extends Controller
{
    private const SIN_PARTIDOS_PROGRAMADOS = 'No hay partidos programados para la fecha seleccionada.';

    public function __construct(
        private readonly ResumenFechaService $resumenes,
        private readonly EnvioSuscriptoresService $envios,
    ) {}

    /**
     * Valida que la fecha tenga resultados y cuenta los suscriptores que van a recibir el resumen,
     * para mostrarlo al administrador antes de confirmar el envío.
     */
    public function contarResumenFecha(ResumenFechaRequest $request): JsonResponse
    {
        $idEdicion = $this->resumenes->idUltimaEdicion();

        if ($idEdicion === null) {
            return response()->json([
                'success' => false,
                'count' => 0,
                'message' => 'No hay ediciones disponibles para enviar notificaciones.',
            ], 422);
        }

        $resumen = $this->resumenes->armar($idEdicion, $request->idFecha(), $request->idCategoria());

        if ($resumen === null) {
            return response()->json([
                'success' => false,
                'count' => 0,
                'message' => 'No hay partidos con resultado cargado para la fecha seleccionada.',
            ], 422);
        }

        $destinatarios = $this->envios->destinatariosResumenFecha();
        $envio = $this->envios->envioResumenFecha($resumen['id_fecha'], $resumen['id_categoria']);

        return response()->json([
            'success' => true,
            'destinatarios' => $destinatarios,
            'partidos_count' => $resumen['partidos_count'],
            'fecha_nombre' => $resumen['fecha_nombre'],
            'categoria_nombre' => $resumen['categoria_nombre'],
            'envio' => $envio === null ? null : [
                'estado' => $envio->estado,
                'destinatarios' => $envio->destinatarios,
                'enviado_at' => $envio->enviado_at?->format('d/m/Y H:i'),
            ],
            'message' => "Se enviará a {$destinatarios} destinatario(s).",
        ]);
    }

    /**
     * Registra el envío del resumen y lo deja en cola. Cada fecha y categoría sale una sola vez;
     * solo se puede volver a pedir uno que falló.
     */
    public function enviarResumenFecha(ResumenFechaRequest $request): JsonResponse
    {
        $idEdicion = $this->resumenes->idUltimaEdicion();
        $resumen = $idEdicion === null
            ? null
            : $this->resumenes->armar($idEdicion, $request->idFecha(), $request->idCategoria());

        if ($resumen === null) {
            return response()->json(['success' => false, 'message' => 'No hay resultados para enviar en esa fecha.'], 422);
        }

        if ($this->envios->destinatariosResumenFecha() === 0) {
            return response()->json(['success' => false, 'message' => 'Todavía nadie eligió recibir el resumen de la fecha.'], 422);
        }

        try {
            $envio = $this->envios->registrarResumenFecha($resumen, $request->user());
        } catch (Throwable $error) {
            Log::error('No se pudo encolar el resumen de fecha para los suscriptores.', [
                'error' => $error::class,
                'idFecha' => $resumen['id_fecha'],
                'idCategoria' => $resumen['id_categoria'],
            ]);

            return response()->json(['success' => false, 'message' => 'No se pudo dejar el envío en cola. Probá de nuevo en unos minutos.'], 503);
        }

        if ($envio === null) {
            $previo = $this->envios->envioResumenFecha($resumen['id_fecha'], $resumen['id_categoria']);

            return response()->json([
                'success' => false,
                'message' => $previo?->estado === EnvioSuscriptores::ESTADO_ENVIADO
                    ? 'Este resumen ya se envió a los suscriptores el '.$previo->enviado_at?->format('d/m/Y H:i').'.'
                    : 'Este resumen ya está en cola para enviarse.',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'envio_id' => $envio->id,
            'estado' => $envio->estado,
            'destinatarios' => $envio->destinatarios,
            'message' => "El resumen quedó en cola para {$envio->destinatarios} destinatario(s): sale en el próximo minuto.",
        ], 202);
    }

    /**
     * Valida que la fecha tenga partidos programados y cuenta los suscriptores que eligieron la
     * próxima fecha, para mostrarlo antes de confirmar el envío.
     */
    public function contarProximaFecha(ResumenFechaRequest $request): JsonResponse
    {
        $proximaFecha = $this->proximaFecha($request);

        if ($proximaFecha === null) {
            return response()->json(['success' => false, 'count' => 0, 'message' => self::SIN_PARTIDOS_PROGRAMADOS], 422);
        }

        $destinatarios = $this->envios->destinatarios('proxima_fecha');
        $envio = $this->envios->envioProximaFecha($proximaFecha['id_fecha'], $proximaFecha['id_categoria']);

        return response()->json([
            'success' => true,
            'destinatarios' => $destinatarios,
            'partidos_count' => $proximaFecha['partidos_count'],
            'fecha_nombre' => $proximaFecha['fecha_nombre'],
            'categoria_nombre' => $proximaFecha['categoria_nombre'],
            'envio' => $envio === null ? null : [
                'estado' => $envio->estado,
                'destinatarios' => $envio->destinatarios,
                'enviado_at' => $envio->enviado_at?->format('d/m/Y H:i'),
            ],
            'message' => "Se enviará a {$destinatarios} destinatario(s).",
        ]);
    }

    /**
     * Registra el envío de la próxima fecha y lo deja en cola. Cada fecha y categoría sale una sola
     * vez; solo se puede volver a pedir una que falló.
     */
    public function enviarProximaFecha(ResumenFechaRequest $request): JsonResponse
    {
        $proximaFecha = $this->proximaFecha($request);

        if ($proximaFecha === null) {
            return response()->json(['success' => false, 'message' => self::SIN_PARTIDOS_PROGRAMADOS], 422);
        }

        if ($this->envios->destinatarios('proxima_fecha') === 0) {
            return response()->json(['success' => false, 'message' => 'Todavía nadie eligió recibir la próxima fecha.'], 422);
        }

        try {
            $envio = $this->envios->registrarProximaFecha($proximaFecha, $request->user());
        } catch (Throwable $error) {
            Log::error('No se pudo encolar la próxima fecha para los suscriptores.', [
                'error' => $error::class,
                'idFecha' => $proximaFecha['id_fecha'],
                'idCategoria' => $proximaFecha['id_categoria'],
            ]);

            return response()->json(['success' => false, 'message' => 'No se pudo dejar el envío en cola. Probá de nuevo en unos minutos.'], 503);
        }

        if ($envio === null) {
            $previo = $this->envios->envioProximaFecha($proximaFecha['id_fecha'], $proximaFecha['id_categoria']);

            return response()->json([
                'success' => false,
                'message' => $previo?->estado === EnvioSuscriptores::ESTADO_ENVIADO
                    ? 'Esta próxima fecha ya se envió a los suscriptores el '.$previo->enviado_at?->format('d/m/Y H:i').'.'
                    : 'Esta próxima fecha ya está en cola para enviarse.',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'envio_id' => $envio->id,
            'estado' => $envio->estado,
            'destinatarios' => $envio->destinatarios,
            'message' => "La próxima fecha quedó en cola para {$envio->destinatarios} destinatario(s): sale en el próximo minuto.",
        ], 202);
    }

    /** Suscriptores que eligieron los avisos del torneo, para mostrarlo antes de confirmar. */
    public function contarAvisoTorneo(): JsonResponse
    {
        $destinatarios = $this->envios->destinatarios('avisos');

        return response()->json([
            'success' => true,
            'destinatarios' => $destinatarios,
            'message' => "Se enviará a {$destinatarios} destinatario(s).",
        ]);
    }

    /**
     * Registra el aviso libre del torneo y lo deja en cola. El identificador de la carga del
     * formulario evita mandarlo dos veces; solo se puede volver a pedir uno que falló.
     */
    public function enviarAvisoTorneo(AvisoTorneoRequest $request): JsonResponse
    {
        if ($this->envios->destinatarios('avisos') === 0) {
            return response()->json(['success' => false, 'message' => 'Todavía nadie eligió recibir los avisos del torneo.'], 422);
        }

        try {
            $envio = $this->envios->registrarAvisoTorneo($request->idEnvio(), $request->titulo(), $request->mensaje(), $request->user());
        } catch (Throwable $error) {
            Log::error('No se pudo encolar el aviso del torneo para los suscriptores.', ['error' => $error::class]);

            return response()->json(['success' => false, 'message' => 'No se pudo dejar el envío en cola. Probá de nuevo en unos minutos.'], 503);
        }

        if ($envio === null) {
            $previo = $this->envios->envio(EnvioSuscriptores::claveAvisoTorneo($request->idEnvio()));

            return response()->json([
                'success' => false,
                'message' => $previo?->estado === EnvioSuscriptores::ESTADO_ENVIADO
                    ? 'Este aviso ya se envió a los suscriptores el '.$previo->enviado_at?->format('d/m/Y H:i').'.'
                    : 'Este aviso ya está en cola para enviarse.',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'envio_id' => $envio->id,
            'estado' => $envio->estado,
            'destinatarios' => $envio->destinatarios,
            'message' => "El aviso quedó en cola para {$envio->destinatarios} destinatario(s): sale en el próximo minuto.",
        ], 202);
    }

    /** @return array<string, mixed>|null */
    private function proximaFecha(ResumenFechaRequest $request): ?array
    {
        $idEdicion = $this->resumenes->idUltimaEdicion();

        return $idEdicion === null
            ? null
            : $this->resumenes->armarProximaFecha($idEdicion, $request->idFecha(), $request->idCategoria());
    }
}
