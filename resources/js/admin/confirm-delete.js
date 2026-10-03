/**
 * Confirmación de borrado para los formularios `[data-confirm-delete]` del
 * panel (x-ui.confirm-delete, T3 del rediseño). El <form> ya es real (POST +
 * @method('DELETE') + @csrf), así que funciona sin JavaScript; esto solo
 * agrega el paso de confirmación antes de enviarlo.
 *
 * No depende de Alpine: usa SweetAlert2 (`window.Swal`) cuando está
 * disponible y cae a `window.confirm` en caso contrario, para que un borrado
 * nunca quede sin protección. Se registra una única vez a nivel de
 * documento (delegación de eventos) para que cualquier formulario agregado
 * después (paginación, renderizados dinámicos) quede cubierto sin volver a
 * inicializar nada.
 */

const MENSAJE_POR_DEFECTO = '¿Confirmás esta acción? No se puede deshacer.';

/**
 * @param {string} mensaje
 * @returns {Promise<boolean>}
 */
function confirmarBorrado(mensaje) {
    if (window.Swal) {
        return window.Swal
            .fire({
                title: 'Confirmar eliminación',
                text: mensaje,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc2626',
            })
            .then((resultado) => resultado.isConfirmed)
            // Si SweetAlert falla, el borrado no puede quedar mudo: se pregunta con confirm().
            .catch(() => window.confirm(mensaje));
    }

    return Promise.resolve(window.confirm(mensaje));
}

// Fase de captura: cancela el envío antes de que el listener por formulario
// del loader global (components/loader) lo vea y deje la pantalla bloqueada
// cuando el usuario cancela.
document.addEventListener('submit', (evento) => {
    const formulario = evento.target;

    if (!(formulario instanceof HTMLFormElement) || !formulario.matches('[data-confirm-delete]')) {
        return;
    }

    evento.preventDefault();

    const mensaje = formulario.dataset.confirmMessage || MENSAJE_POR_DEFECTO;

    confirmarBorrado(mensaje).then((confirmado) => {
        if (confirmado) {
            // form.submit() no dispara "submit", así que el loader global se muestra acá.
            document.getElementById('loader')?.classList.remove('hidden');
            formulario.submit();
        }
    });
}, true);
