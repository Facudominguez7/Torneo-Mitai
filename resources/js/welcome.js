const button = document.querySelector(
    '[data-collapse-toggle="navbar-dropdown"]'
);
const botonEdiciones = document.querySelector(
    '[data-dropdown-toggle="dropdownEdiciones"]'
);
const botonUser = document.querySelector('[data-dropdown-toggle="dropdownUser"]');

const menu = document.getElementById("navbar-dropdown");
const menuEdiciones = document.getElementById("dropdownEdiciones");
const menuUser = document.getElementById("dropdownUser");

button.addEventListener("click", function () {
    menu.classList.toggle("hidden");
});

botonEdiciones.addEventListener("click", function (event) {
    const buttonRect = botonEdiciones.getBoundingClientRect();

    if (window.innerWidth < 768) {
        menuEdiciones.style.top = `${buttonRect.bottom}px`;
        menuEdiciones.style.left = "0";
        menuEdiciones.style.width = "100%";
    } else {
        const navRect = document.querySelector("nav").getBoundingClientRect();
        const menuWidth = navRect.width * 0.1; // Ancho ajustado

        menuEdiciones.style.top = `${buttonRect.bottom}px`;
        menuEdiciones.style.left = `${buttonRect.left}px`; // Ajuste de posición a la izquierda del botón
        menuEdiciones.style.width = `${menuWidth}px`;
    }

    menuEdiciones.classList.toggle("hidden");
});
document.addEventListener("click", function (event) {
    const isClickInsideEdiciones = menuEdiciones.contains(event.target);
    const isClickOnButtonEdiciones = event.target === botonEdiciones;
    const isClickInsideMenuUser = menuUser.contains(event.target);
    const isClickOnButtonUser = event.target === botonUser;

    if (!isClickInsideMenuCategorias && !isClickOnButtonCategorias) {
        menuCategorias.classList.add("hidden");
    }
    if (!isClickInsideEdiciones && !isClickOnButtonEdiciones) {
        menuEdiciones.classList.add("hidden");
    }
    if (!isClickInsideMenuUser && !isClickOnButtonUser) {
        menuUser.classList.add("hidden");
    }
});

function descargarPDF() {
    // Crea un enlace invisible
    var enlace = document.createElement("a");
    enlace.href = ""; // Reemplaza 'ruta/al/archivo.pdf' con la URL del archivo PDF
    enlace.download = "ReglamentoMitaiCup.pdf"; // Establece el nombre del archivo
    document.body.appendChild(enlace);
    enlace.click();
    document.body.removeChild(enlace);
}
