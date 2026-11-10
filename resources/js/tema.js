// Tema binario claro ⇄ oscuro del sitio público y del panel: este botón
// escribe data-tema en <html> y guarda la elección. Ya no hay estado
// "sistema": head.blade.php aplica data-tema temprano (claro por defecto)
// para que no haya flash del tema contrario al cargar.
(function () {
    const root = document.documentElement;
    const boton = document.getElementById('btn-tema');
    if (!boton) {
        return;
    }

    const icono = document.getElementById('icono-tema');
    const CLAVE_TEMA = 'mitai_tema';
    const estados = ['claro', 'oscuro'];

    const ICONO_SOL = '<circle cx="7" cy="7" r="3" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M7 .8v1.7M7 11.5v1.7M.8 7h1.7M11.5 7h1.7M2.6 2.6l1.2 1.2M10.2 10.2l1.2 1.2M11.4 2.6l-1.2 1.2M3.8 10.2l-1.2 1.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>';
    const ICONO_LUNA = '<path d="M11.8 8.6A5 5 0 015.4 2.2 5 5 0 1011.8 8.6z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>';

    function aplicar(estado) {
        root.setAttribute('data-tema', estado);
        boton.setAttribute('aria-checked', estado === 'oscuro' ? 'true' : 'false');
        if (icono) {
            icono.innerHTML = estado === 'claro' ? ICONO_SOL : ICONO_LUNA;
        }
    }

    // La elección sobrevive recargas; sin estado guardado (o con localStorage
    // bloqueado) queda el tema claro.
    let indice = 0;
    try {
        indice = Math.max(0, estados.indexOf(localStorage.getItem(CLAVE_TEMA)));
    } catch {
        indice = 0;
    }

    boton.addEventListener('click', function () {
        indice = (indice + 1) % estados.length;
        try {
            localStorage.setItem(CLAVE_TEMA, estados[indice]);
        } catch {
            // Sin almacenamiento disponible: el override vale hasta recargar.
        }
        aplicar(estados[indice]);
    });

    aplicar(estados[indice]);
})();
