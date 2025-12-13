// Admin.js - Sidebar toggle
document.addEventListener('DOMContentLoaded', function () {
    const dropdownBtn = document.querySelector('[data-collapse-toggle="dropdown-example"]');
    const dropdown = document.getElementById('dropdown-example');

    if (dropdownBtn && dropdown) {
        dropdownBtn.addEventListener('click', () => dropdown.classList.toggle('hidden'));
    }

    const sidebarBtn = document.querySelector('[data-drawer-toggle="sidebar-multi-level-sidebar"]');
    const sidebar = document.getElementById('sidebar-multi-level-sidebar');

    if (sidebarBtn && sidebar) {
        const links = sidebar.querySelectorAll('a');
        const hide = () => sidebar.classList.add('-translate-x-full');

        sidebarBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('-translate-x-full');
        });

        document.addEventListener('click', (e) => {
            if (!sidebar.contains(e.target) && !sidebarBtn.contains(e.target)) hide();
        });

        links.forEach(link => link.addEventListener('click', hide));
    }
});
