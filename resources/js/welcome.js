// Welcome.js - Menús dropdown
(function () {
    const button = document.querySelector('[data-collapse-toggle="navbar-dropdown"]');
    const menu = document.getElementById('navbar-dropdown');
    const botonEdiciones = document.querySelector('[data-dropdown-toggle="dropdownEdiciones"]');
    const menuEdiciones = document.getElementById('dropdownEdiciones');
    const botonUser = document.querySelector('[data-dropdown-toggle="dropdownUser"]');
    const menuUser = document.getElementById('dropdownUser');

    if (button && menu) {
        button.addEventListener('click', () => menu.classList.toggle('hidden'));
    }

    if (botonEdiciones && menuEdiciones) {
        botonEdiciones.addEventListener('click', function () {
            const rect = botonEdiciones.getBoundingClientRect();
            if (window.innerWidth < 768) {
                menuEdiciones.style.cssText = `top:${rect.bottom}px;left:0;width:100%`;
            } else {
                menuEdiciones.style.cssText = `top:${rect.bottom}px;left:${rect.left}px`;
            }
            menuEdiciones.classList.toggle('hidden');
        });
    }

    document.addEventListener('click', function (e) {
        if (menuEdiciones && botonEdiciones && !menuEdiciones.contains(e.target) && e.target !== botonEdiciones) {
            menuEdiciones.classList.add('hidden');
        }
        if (menuUser && botonUser && !menuUser.contains(e.target) && e.target !== botonUser) {
            menuUser.classList.add('hidden');
        }
    });
})();

function descargarPDF() {
    const a = document.createElement('a');
    a.href = '';
    a.download = 'ReglamentoMitaiCup.pdf';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}
