<?php

namespace App\Http\Controllers;

use App\Http\Requests\EquipoSeguido\ShowRequest;
use App\Services\EquipoSeguidoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class ControladorEquipoSeguido extends Controller
{
    public function __construct(
        private readonly EquipoSeguidoService $equipoSeguidoService,
    ) {}

    public function show(ShowRequest $request): JsonResponse
    {
        try {
            $payload = $this->equipoSeguidoService->resolver(
                $request->string('nombre')->toString(),
                (int) $request->integer('idEdicion'),
                $request->validated('idCategoria') !== null
                    ? (int) $request->validated('idCategoria')
                    : null,
            );

            return response()->json($payload);
        } catch (Throwable $exception) {
            Log::error('Error al consultar el equipo seguido.', [
                'nombre' => $request->string('nombre')->toString(),
                'idEdicion' => $request->integer('idEdicion'),
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo cargar el equipo seguido en este momento.',
            ], 500);
        }
    }
}
