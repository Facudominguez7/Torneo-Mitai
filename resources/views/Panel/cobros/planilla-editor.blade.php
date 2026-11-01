<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Deudas de planilla · Mitaí</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-900">
    <main class="mx-auto max-w-5xl p-4 sm:p-6">
        <h1 class="text-2xl font-semibold">Deudas de planilla</h1>
        <p class="mt-1 text-sm text-gray-600">Partido {{ $partido->id }}</p>
        <section class="mt-4 border border-gray-200 bg-white lg:sticky lg:top-0" aria-label="Estado de deudas de los equipos">
            <div data-cobros-seccion aria-live="polite"
                data-url="{{ route('planilla.deudas', ['idEdicion' => $partido->idEdicion, 'tipoPartido' => $tipoPartido, 'partidoId' => $partido->id]) }}">
                @include('Panel.cobros.franja')
            </div>
        </section>
    </main>
    <script>
        (() => {
            const seccion = document.querySelector('[data-cobros-seccion]');
            let consultando = false;
            const refrescar = async () => {
                if (consultando || document.visibilityState !== 'visible') return;
                consultando = true;
                try {
                    const respuesta = await fetch(seccion.dataset.url, { headers: { Accept: 'text/html' }, cache: 'no-store' });
                    if (!respuesta.ok || respuesta.redirected) return;
                    const plantilla = document.createElement('template');
                    plantilla.innerHTML = (await respuesta.text()).trim();
                    const grilla = plantilla.content.querySelector('[data-cobros-grilla]');
                    if (!grilla || grilla.dataset.firma === seccion.querySelector('[data-cobros-grilla]')?.dataset.firma) return;
                    const selecciones = [...seccion.querySelectorAll('[data-equipo-franja]')].map((fila) => [fila.dataset.equipoFranja, fila.querySelector('[data-deuda-selector]')?.value]);
                    seccion.replaceChildren(plantilla.content);
                    requestAnimationFrame(() => selecciones.forEach(([equipo, valor]) => {
                        const selector = seccion.querySelector(`[data-equipo-franja="${equipo}"] [data-deuda-selector]`);
                        if (selector && [...selector.options].some((opcion) => opcion.value === valor)) {
                            selector.value = valor;
                            selector.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }));
                } catch {
                    // Se reintenta en el próximo intervalo.
                } finally {
                    consultando = false;
                }
            };
            setInterval(refrescar, 1000);
            document.addEventListener('visibilitychange', refrescar);
        })();
    </script>
</body>
</html>
