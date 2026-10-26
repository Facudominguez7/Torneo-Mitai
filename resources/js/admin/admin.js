/**
 * Comportamientos base del panel de administración.
 *
 * El drawer móvil, el backdrop, el bloqueo de scroll, el cierre con Escape
 * y el estado abierto/cerrado de cada grupo de navegación se manejan de
 * forma declarativa con Alpine directamente en resources/views/Panel/admin.blade.php
 * y resources/views/Panel/partials/sidebar.blade.php. El resaltado del enlace
 * activo también se resuelve en el servidor (aria-current + clases de Blade).
 *
 * La confirmación de borrado (x-ui.confirm-delete, T3) sí necesita JS real
 * (no depende de Alpine) y vive en su propio módulo para mantenerlo testeable
 * y reusable fuera del panel si hiciera falta.
 */
import './confirm-delete';
