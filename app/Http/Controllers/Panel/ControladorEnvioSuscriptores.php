<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Suscripcion\ResumenFechaRequest;
use App\Models\EnvioSuscriptores;
use App\Services\EnvioSuscriptoresService;
use App\Services\ResumenFechaService;
use Illuminate\Http\JsonResponse;

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

        $envio = $this->envios->registrarResumenFecha($resumen, $request->user());

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
}
