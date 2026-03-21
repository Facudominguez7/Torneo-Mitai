<?php

namespace App\Http\Controllers;

use App\Services\PlanillaPublicaService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class ControladorPlanillaPublica extends Controller
{
    public function __construct(
        private readonly PlanillaPublicaService $planillaPublicaService,
    ) {}

    public function show(Request $request, string $tipoPartido, string $publicToken): JsonResponse
    {
        try {
            $payload = $this->planillaPublicaService->obtenerPlanilla($tipoPartido, $publicToken);

            return response()->json($payload);
        } catch (InvalidArgumentException|ModelNotFoundException $exception) {
            return response()->json([
                'message' => 'La planilla solicitada no existe.',
            ], 404);
        } catch (Throwable $exception) {
            Log::error('Error al consultar planilla publica.', [
                'publicToken' => $publicToken,
                'tipoPartido' => $tipoPartido,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo cargar la planilla en este momento.',
            ], 500);
        }
    }
}
