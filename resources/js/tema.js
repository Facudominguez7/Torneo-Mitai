// Tema dual de la home "Tu equipo primero" (artboard 06): el CSS resuelve el
// estado "sistema" con prefers-color-scheme; este botón escribe data-tema en
// <html> para el override manual sistema → claro → oscuro → sistema.
(function () {
    const root = document.documentElement;
    const boton = document.getElementById('btn-tema');
    if (!boton) {
        return;
    }

    const etiqueta = document.getElementById('etiqueta-tema');
    const icono = document.getElementById('icono-tema');
    const estados = ['sistema', 'claro', 'oscuro'];

    const ICONO_SOL = '<circle cx="7" cy="7" r="3" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M7 .8v1.7M7 11.5v1.7M.8 7h1.7M11.5 7h1.7M2.6 2.6l1.2 1.2M10.2 10.2l1.2 1.2M11.4 2.6l-1.2 1.2M3.8 10.2l-1.2 1.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>';
    const ICONO_LUNA = '<path d="M11.8 8.6A5 5 0 015.4 2.2 5 5 0 1011.8 8.6z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>';
    const ICONO_AUTO = ICONO_SOL + '<path d="M9.5 12.2A4.6 4.6 0 001.8 7.9" fill="none" stroke="currentColor" stroke-width="1.3"/>';

    function aplicar(estado) {
        if (estado === 'claro') {
            root.setAttribute('data-tema', 'claro');
        } else if (estado === 'oscuro') {
            root.setAttribute('data-tema', 'oscuro');
        } else {
            root.removeAttribute('data-tema');
        }
        if (etiqueta) {
            etiqueta.textContent = estado === 'sistema' ? 'Sistema' : estado.charAt(0).toUpperCase() + estado.slice(1);
        }
        if (icono) {
            icono.innerHTML = estado === 'claro' ? ICONO_SOL : estado === 'oscuro' ? ICONO_LUNA : ICONO_AUTO;
        }
    }

    let indice = 0;
    boton.addEventListener('click', function () {
        indice = (indice + 1) % estados.length;
        aplicar(estados[indice]);
    });

    aplicar(estados[indice]);

    // Si el usuario está en "sistema" y cambia el tema del SO, el CSS lo
    // refleja solo; acá solo refrescamos el icono/etiqueta.
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
        if (estados[indice] === 'sistema') {
            aplicar('sistema');
        }
    });
})();
