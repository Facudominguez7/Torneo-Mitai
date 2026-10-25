<?php

namespace App\Http\Controllers;

use App\Http\Requests\Suscripcion\PreferenciasRequest;
use App\Http\Requests\Suscripcion\SuscribirRequest;
use App\Models\Edicion;
use App\Models\Suscriptor;
use App\Services\EquiposDeEdicionService;
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

    public function preferencias(Suscriptor $suscriptor, EquiposDeEdicionService $equiposDeEdicion): View
    {
        $suscriptor->load('equipos.categoria:id,nombreCategoria');
        $ultimaEdicion = Edicion::query()->latest('id')->first();
        $seguidos = $suscriptor->equipos->pluck('id')->all();

        return view('suscripcion.preferencias', [
            'suscriptor' => $suscriptor,
            'equiposDisponibles' => $ultimaEdicion
                ? $equiposDeEdicion->participantes((int) $ultimaEdicion->id)->reject(fn (array $equipo): bool => in_array($equipo['id'], $seguidos, true))
                : collect(),
            'urlPreferencias' => $this->suscripciones->urlPreferencias($suscriptor),
            'urlBaja' => $this->suscripciones->urlBaja($suscriptor),
        ]);
    }

    public function actualizarPreferencias(PreferenciasRequest $request, Suscriptor $suscriptor): RedirectResponse
    {
        $this->suscripciones->actualizarPreferencias(
            $suscriptor,
            array_map('intval', $request->validated('equipos', [])),
            $request->validated('avisos') ?? [],
        );

        return redirect()->to($this->suscripciones->urlPreferencias($suscriptor))
            ->with('suscripcion', 'Guardamos tus preferencias.');
    }

    /** Página del enlace de baja del pie del correo: confirma con un botón para que un clic accidental no dé de baja. */
    public function confirmarBaja(Suscriptor $suscriptor): View
    {
        return view('suscripcion.baja', [
            'suscriptor' => $suscriptor,
            'urlBaja' => $this->suscripciones->urlBaja($suscriptor),
            'urlPreferencias' => $this->suscripciones->urlPreferencias($suscriptor),
        ]);
    }

    public function baja(Suscriptor $suscriptor): RedirectResponse
    {
        $this->suscripciones->darDeBaja($suscriptor);

        return redirect('/')->with('suscripcion', 'Te diste de baja. No vas a recibir más avisos por correo.');
    }
}
