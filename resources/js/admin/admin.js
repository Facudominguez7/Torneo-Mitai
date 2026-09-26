/**
 * Comportamientos base del panel de administración.
 *
 * Mantiene accesible la navegación lateral en mobile y desktop,
 * y resalta de forma automática la opción activa para mejorar
 * la orientación del usuario dentro del módulo actual.
 */
document.addEventListener('DOMContentLoaded', () => {
    const dropdownBtn = document.querySelector('[data-collapse-toggle="dropdown-example"]');
    const dropdown = document.getElementById('dropdown-example');

    if (dropdownBtn && dropdown) {
        dropdownBtn.setAttribute('aria-expanded', dropdown.classList.contains('hidden') ? 'false' : 'true');

        dropdownBtn.addEventListener('click', () => {
            dropdown.classList.toggle('hidden');
            dropdownBtn.setAttribute('aria-expanded', dropdown.classList.contains('hidden') ? 'false' : 'true');
        });
    }

    const sidebarBtn = document.querySelector('[data-drawer-toggle="sidebar-multi-level-sidebar"]');
    const sidebar = document.getElementById('sidebar-multi-level-sidebar');
    const sidebarBackdrop = document.getElementById('sidebar-backdrop');

    if (!sidebarBtn || !sidebar) {
        return;
    }

    const links = sidebar.querySelectorAll('a[href]');

    /**
     * Determina si el layout está en breakpoint desktop.
     *
     * @returns {boolean}
     */
    const esDesktop = () => window.matchMedia('(min-width: 1024px)').matches;

    /**
     * Sincroniza overlay y scroll del body con el estado del sidebar en mobile.
     */
    const sincronizarBackdrop = () => {
        if (!sidebarBackdrop) {
            return;
        }

        const sidebarOculto = sidebar.classList.contains('-translate-x-full');

        if (!esDesktop() && !sidebarOculto) {
            sidebarBackdrop.classList.remove('opacity-0', 'pointer-events-none');
            sidebarBackdrop.classList.add('opacity-100');
            document.body.classList.add('overflow-hidden');
            return;
        }

        sidebarBackdrop.classList.add('opacity-0', 'pointer-events-none');
        sidebarBackdrop.classList.remove('opacity-100');
        document.body.classList.remove('overflow-hidden');
    };

    /**
     * Oculta la barra lateral solo en mobile para no cortar el flujo.
     */
    const ocultarSidebar = () => {
        if (!esDesktop()) {
            sidebar.classList.add('-translate-x-full');
            sidebarBtn.setAttribute('aria-expanded', 'false');
            sincronizarBackdrop();
        }
    };

    /**
     * Alterna la visibilidad del sidebar respetando `aria-expanded`.
     */
    const alternarSidebar = () => {
        sidebar.classList.toggle('-translate-x-full');
        sidebarBtn.setAttribute('aria-expanded', sidebar.classList.contains('-translate-x-full') ? 'false' : 'true');
        sincronizarBackdrop();
    };

    sidebarBtn.setAttribute('aria-expanded', sidebar.classList.contains('-translate-x-full') ? 'false' : 'true');
    sincronizarBackdrop();

    sidebarBtn.addEventListener('click', (event) => {
        event.stopPropagation();
        alternarSidebar();
    });

    document.addEventListener('click', (event) => {
        if (!esDesktop() && !sidebar.contains(event.target) && !sidebarBtn.contains(event.target)) {
            ocultarSidebar();
        }
    });

    sidebarBackdrop?.addEventListener('click', () => {
        ocultarSidebar();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            ocultarSidebar();
            if (dropdown && !dropdown.classList.contains('hidden')) {
                dropdown.classList.add('hidden');
                dropdownBtn?.setAttribute('aria-expanded', 'false');
            }
        }
    });

    window.addEventListener('resize', () => {
        if (esDesktop()) {
            sidebar.classList.remove('-translate-x-full');
            sidebarBtn.setAttribute('aria-expanded', 'true');
            sincronizarBackdrop();
            return;
        }

        sidebar.classList.add('-translate-x-full');
        sidebarBtn.setAttribute('aria-expanded', 'false');
        sincronizarBackdrop();
    });

    links.forEach((link) => {
        link.addEventListener('click', ocultarSidebar);

        const href = link.getAttribute('href');
        if (!href || href === '#') {
            return;
        }

        let targetUrl;
        try {
            targetUrl = new URL(href, window.location.origin);
        } catch (error) {
            return;
        }

        const currentPath = window.location.pathname.replace(/\/$/, '');
        const targetPath = targetUrl.pathname.replace(/\/$/, '');

        if (!targetPath || targetPath === '/') {
            return;
        }

        if (currentPath === targetPath || currentPath.startsWith(`${targetPath}/`)) {
            link.classList.add('bg-blue-50', 'text-blue-700', 'font-semibold');
            const icon = link.querySelector('svg');
            if (icon) {
                icon.classList.remove('text-gray-500');
                icon.classList.add('text-blue-600');
            }
        }
    });
});
