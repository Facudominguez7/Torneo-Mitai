<?php

namespace App\Http\Controllers;

use App\Http\Requests\Suscripcion\SuscribirRequest;
use App\Models\Suscriptor;
use App\Services\SuscripcionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ControladorSuscripcion extends Controller
{
    private const MENSAJE_ACTIVO = "¡Listo! Ya estás suscripto a los avisos de Torneos Mita'í.";

    public function __construct(private readonly SuscripcionService $suscripciones) {}

    /** Página para suscribirse sin seguir un equipo (los resultados se eligen desde "Seguir mi equipo"). */
    public function create(): View
    {
        return view('suscripcion.create');
    }

    public function store(SuscribirRequest $request): RedirectResponse
    {
        $email = $request->validated('email');

        $activo = $this->suscripciones->suscribir(
            $email,
            array_map('intval', $request->validated('equipos', [])),
            $request->has('avisos') ? ($request->validated('avisos') ?? []) : null,
            $request->user(),
        );

        // Mismo mensaje exista o no el suscriptor, para no revelar qué correos están anotados.
        return back()->with('suscripcion', $activo
            ? self::MENSAJE_ACTIVO
            : "Te enviamos un correo a {$email}. Abrilo y confirmá la suscripción.");
    }

    public function confirmar(Request $request, Suscriptor $suscriptor): RedirectResponse
    {
        $confirmado = $this->suscripciones->confirmar($suscriptor, $request->integer('emitido'));

        return redirect('/')->with('suscripcion', $confirmado
            ? self::MENSAJE_ACTIVO
            : 'Ese enlace ya no sirve. Volvé a suscribirte para recibir uno nuevo.');
    }

    public function baja(Suscriptor $suscriptor): RedirectResponse
    {
        $this->suscripciones->darDeBaja($suscriptor);

        return redirect('/')->with('suscripcion', 'Te diste de baja. No vas a recibir más avisos por correo.');
    }
}
