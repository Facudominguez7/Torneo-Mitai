document.addEventListener('DOMContentLoaded', function () {
    const button = document.querySelector('[data-collapse-toggle="dropdown-example"]');
    const dropdown = document.getElementById('dropdown-example');

    button.addEventListener('click', function () {
        dropdown.classList.toggle('hidden');
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const button = document.querySelector('[data-drawer-toggle="sidebar-multi-level-sidebar"]');
    const sidebar = document.getElementById('sidebar-multi-level-sidebar');
    const links = sidebar.querySelectorAll('a');

    // Función para ocultar el sidebar
    function hideSidebar() {
        if (!sidebar.classList.contains('-translate-x-full')) {
            sidebar.classList.add('-translate-x-full');
        }
    }

    // Toggle sidebar al hacer clic en el botón
    button.addEventListener('click', function (event) {
        event.stopPropagation();
        sidebar.classList.toggle('-translate-x-full');
    });

    // Ocultar sidebar al hacer clic fuera de él
    document.addEventListener('click', function (event) {
        if (!sidebar.contains(event.target) && !button.contains(event.target)) {
            hideSidebar();
        }
    });

    // Ocultar sidebar al hacer clic en cualquier enlace dentro de él
    links.forEach(function (link) {
        link.addEventListener('click', function () {
            hideSidebar();
        });
    });
});

