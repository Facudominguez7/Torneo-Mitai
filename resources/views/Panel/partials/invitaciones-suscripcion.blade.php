{{--
    Sección "Invitaciones" del panel de suscriptores (T3, S2): cómo vienen las invitaciones diarias
    que manda el scheduler y cuántos usuarios faltan invitar. Nunca muestra correos.
--}}
@php
    $horasEnlace = $invitaciones['horas_confirmacion'];
    $limiteRebote = number_format($invitaciones['tasa_rebote_maxima'], 0, ',', '.');
    $alertaRebote = $invitaciones['alerta_rebote'];
    $tiles = [
        ['clave' => 'invitados', 'etiqueta' => 'Invitados', 'detalle' => 'Invitaciones enviadas', 'valor' => (string) $invitaciones['invitados']],
        ['clave' => 'confirmados', 'etiqueta' => 'Confirmados', 'detalle' => 'Ya reciben los avisos', 'valor' => (string) $invitaciones['confirmados']],
        ['clave' => 'pendientes', 'etiqueta' => 'Pendientes', 'detalle' => 'Con enlace vigente ('.$horasEnlace.' h)', 'valor' => (string) $invitaciones['pendientes']],
        ['clave' => 'vencidos', 'etiqueta' => 'Vencidos', 'detalle' => 'Sin confirmar pasadas las '.$horasEnlace.' h', 'valor' => (string) $invitaciones['vencidos']],
        ['clave' => 'rebotes', 'etiqueta' => 'Rebotes', 'detalle' => 'Correos que no llegaron', 'valor' => (string) $invitaciones['rebotes']],
        ['clave' => 'quejas', 'etiqueta' => 'Quejas por spam', 'detalle' => 'Marcaron el correo como spam', 'valor' => (string) $invitaciones['quejas']],
        ['clave' => 'tasa-rebote', 'etiqueta' => 'Tasa de rebote', 'detalle' => 'Sobre los invitados; Resend admite hasta '.$limiteRebote.' %', 'valor' => $invitaciones['tasa_rebote'] === null ? '—' : number_format($invitaciones['tasa_rebote'], 1, ',', '.').' %'],
        ['clave' => 'faltan-invitar', 'etiqueta' => 'Faltan invitar', 'detalle' => 'Usuarios con cuenta que todavía no recibieron la invitación', 'valor' => (string) $invitaciones['faltan_invitar']],
    ];
@endphp

<section class="mt-6 rounded-xl border border-pa-linea bg-pa-superficie p-4">
    <div>
        <h2 class="text-base font-bold text-pa-tinta">Invitaciones</h2>
        <p class="mt-1 text-sm text-pa-tinta-suave">Invitaciones diarias a los usuarios con cuenta que todavía no reciben los avisos por correo.</p>
    </div>

    <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
        @foreach($tiles as $tile)
            @php
                $enAlerta = $alertaRebote && $tile['clave'] === 'tasa-rebote';
            @endphp
            <div class="min-w-0 rounded-lg border p-3 {{ $enAlerta ? 'border-yellow-300 bg-yellow-50' : 'border-pa-linea bg-pa-fila-bg' }}">
                <dt class="break-words text-[11px] font-semibold uppercase tracking-wide text-pa-tinta-suave">{{ $tile['etiqueta'] }}</dt>
                <dd class="mt-1 break-words text-2xl font-extrabold tabular-nums text-brand-navy" data-estadistica="{{ $tile['clave'] }}">{{ $tile['valor'] }}</dd>
                <p class="mt-1 text-[11px] leading-snug text-pa-tinta-suave">{{ $tile['detalle'] }}</p>
                @if($enAlerta)
                    <x-ui.badge variant="warning" class="mt-2">Supera el {{ $limiteRebote }} % de Resend</x-ui.badge>
                @endif
            </div>
        @endforeach
    </dl>

    @if($alertaRebote)
        <p class="mt-3 rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-800">
            Resend admite hasta un {{ $limiteRebote }} % de rebotes: por encima de esa tasa pueden suspenderse los envíos.
            Conviene revisar las direcciones antes de seguir invitando.
        </p>
    @endif

    <p class="mt-3 text-sm text-pa-tinta-cuerpo">
        Próximo lote:
        @if($invitaciones['faltan_invitar'] === 0)
            no queda nadie por invitar
        @else
            todos los días a las {{ $invitaciones['hora_lote'] }}, hasta {{ $invitaciones['invitaciones_por_lote'] }} invitaciones
        @endif
    </p>
</section>
