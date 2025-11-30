<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogController extends Controller
{ public function Testloginfo(Request $request)
    {
        // Ruta a registrar en el log
        $ruta = 'http://127.0.0.1:8000/Panel/equipoedicion/231';

        try {
            // Registrar información de acceso
            Log::info('Acceso a la ruta: ' . $ruta);

            // Registrar información adicional sobre la solicitud
            Log::info('Detalles de la solicitud', [
                'url_actual' => $request->fullUrl(),
                'metodo' => $request->method(),
                'parametros' => $request->all(),
                'headers' => $request->headers->all(),
                'ip_cliente' => $request->ip()
            ]);

            // Simulación de lógica adicional que podría causar errores
            // Por ejemplo, si quieres verificar algo:
            if (!$request->has('parametro')) {
                throw new \Exception('Falta el parámetro obligatorio "parametro".');
            }

            // Respuesta exitosa
            return response()->json([
                'message' => 'Información registrada en el log correctamente',
                'ruta' => $ruta
            ]);

        } catch (\Exception $e) {
            // Registrar errores en el log
            Log::error('Error al procesar la solicitud: ' . $e->getMessage(), [
                'codigo' => $e->getCode(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine(),
                'traza' => $e->getTraceAsString()
            ]);

            // Respuesta en caso de error
            return response()->json([
                'error' => 'Ocurrió un error al registrar la información',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }
}
