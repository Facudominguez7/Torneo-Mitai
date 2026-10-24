<div class="space-y-4">
    <section aria-label="Cargos del equipo">
        <h3 class="font-semibold">Cargos de esta edición · todos los estados y fechas</h3>
        @forelse ($cargos as $cargo)
            <p class="mt-2 break-words text-sm">{{ $cargo['equipo'] }} · Cargo #{{ $cargo['id'] }} · {{ $cargo['fecha'] ?? 'Sin fecha' }} · {{ ucfirst($cargo['estado']) }}<br>Original $ {{ number_format($cargo['precio_ars'], 0, ',', '.') }} · Aplicado $ {{ number_format($cargo['aplicado_ars'], 0, ',', '.') }} · Eximido $ {{ number_format($cargo['eximido_ars'], 0, ',', '.') }} · Deuda $ {{ number_format($cargo['deuda_ars'], 0, ',', '.') }}</p>
        @empty <p>No hay cargos.</p> @endforelse
    </section>
    <section aria-label="Ingresos del equipo">
        <h3 class="font-semibold">Pagos recibidos</h3>
        @forelse ($pagos as $pago)
            @php($usos = $aplicaciones->get($pago->id, collect()))
            <p class="mt-2 break-words text-sm">Pago #{{ $pago->id }} · {{ $pago->recibido_at }} · {{ $pago->medio }} · $ {{ number_format($pago->importe_ars, 0, ',', '.') }} ARS<br>Crédito disponible de este pago: $ {{ number_format($pago->importe_ars - $usos->sum('importe_ars'), 0, ',', '.') }}</p>
            <ul class="list-inside list-disc text-sm">
                @foreach ($usos as $uso)<li>Cargo #{{ $uso->cargo_id }}: aplicado $ {{ number_format($uso->importe_ars, 0, ',', '.') }}</li>@endforeach
            </ul>
        @empty <p class="text-sm">Sin pagos recibidos.</p> @endforelse
    </section>
    <section aria-label="Exenciones del equipo">
        <h3 class="font-semibold">Exenciones y política futura</h3>
        @foreach ($exenciones as $exencion)
            <p class="mt-2 whitespace-pre-wrap break-words text-sm">Cargo #{{ $exencion->cargo_id }} · $ {{ number_format($exencion->importe_ars, 0, ',', '.') }} · Motivo: {{ $exencion->motivo ?? 'Sin motivo' }}</p>
        @endforeach
        <p class="mt-2 whitespace-pre-wrap break-words text-sm">{{ $politica ? 'Política vigente para cargos futuros. Motivo: '.($politica->motivo ?? 'Sin motivo') : 'Sin política de exención futura.' }}</p>
        <p class="mt-2 text-sm">La política futura no elimina deuda actual. No hay reversión de pagos disponible.</p>
    </section>
    <section aria-label="Historial auditable">
        <h3 class="font-semibold">Historial</h3>
        @forelse ($eventos as $evento)
            <p class="mt-2 break-words text-sm">#{{ $evento->id }} · {{ $evento->created_at }} · {{ $evento->accion }} · {{ $evento->actor_nombre }}</p>
        @empty <p class="text-sm">Sin eventos.</p> @endforelse
    </section>
</div>
