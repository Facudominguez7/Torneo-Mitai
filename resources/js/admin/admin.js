/**
 * Comportamientos base del panel de administración.
 *
 * El drawer móvil, el backdrop, el bloqueo de scroll, el cierre con Escape
 * y el estado abierto/cerrado de cada grupo de navegación se manejan de
 * forma declarativa con Alpine directamente en resources/views/Panel/admin.blade.php
 * y resources/views/Panel/partials/sidebar.blade.php. El resaltado del enlace
 * activo también se resuelve en el servidor (aria-current + clases de Blade),
 * así que este archivo no necesita lógica adicional por ahora.
 */
