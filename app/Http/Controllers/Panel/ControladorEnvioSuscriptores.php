<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Suscripcion\ResumenFechaRequest;
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

        $suscriptores = $this->envios->destinatariosResumenFecha();

        return response()->json([
            'success' => true,
            'suscriptores' => $suscriptores,
            'partidos_count' => $resumen['partidos_count'],
            'fecha_nombre' => $resumen['fecha_nombre'],
            'categoria_nombre' => $resumen['categoria_nombre'],
            'message' => "Se enviará a {$suscriptores} suscriptor(es).",
        ]);
    }

    /**
     * Envía el resumen de una fecha a los suscriptores que lo eligieron.
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

        try {
            $envio = $this->envios->enviarResumenFecha($resumen);
        } catch (Throwable $e) {
            // Sin el mensaje del proveedor: puede incluir correos de suscriptores.
            Log::error('No se pudo enviar el resumen de fecha a los suscriptores.', [
                'error' => $e::class,
                'codigo' => $e->getCode(),
                'idFecha' => $resumen['id_fecha'],
                'idCategoria' => $resumen['id_categoria'],
            ]);

            return response()->json(['success' => false, 'message' => 'Resend no aceptó el envío. Probá de nuevo en unos minutos.'], 502);
        }

        if ($envio === null) {
            return response()->json([
                'success' => false,
                'message' => 'Este resumen ya se envió a los suscriptores hace menos de '.EnvioSuscriptoresService::MINUTOS_ENTRE_ENVIOS.' minutos.',
            ], 409);
        }

        return response()->json([
            'success' => true,
            ...$envio,
            'message' => "Resumen enviado a {$envio['enviados']} suscriptor(es).",
        ]);
    }
}
