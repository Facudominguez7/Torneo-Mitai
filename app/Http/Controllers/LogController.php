<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogController extends Controller
{
    public function Testloginfo(Request $request)
    {
        abort_unless(app()->environment('local'), 404);

        $ruta = 'http://127.0.0.1:8000/Panel/equipoedicion/231';

        try {
            Log::info('Acceso a la ruta: '.$ruta);

            Log::info('Detalles de la solicitud', [
                'url_actual' => $request->fullUrl(),
                'metodo' => $request->method(),
                'parametros_presentes' => array_keys($request->except(['password', 'password_confirmation', 'token'])),
                'headers_presentes' => array_keys($request->headers->all()),
                'ip_cliente' => $request->ip(),
            ]);

            if (! $request->has('parametro')) {
                throw new \Exception('Falta el parámetro obligatorio "parametro".');
            }

            return response()->json([
                'message' => 'Información registrada en el log correctamente',
                'ruta' => $ruta,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al procesar la solicitud: '.$e->getMessage(), [
                'codigo' => $e->getCode(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine(),
                'url_actual' => $request->fullUrl(),
                'metodo' => $request->method(),
            ]);

            return response()->json([
                'error' => 'Ocurrió un error al registrar la información',
                'detalle' => $e->getMessage(),
            ], 500);
        }
    }
}
