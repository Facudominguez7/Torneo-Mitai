# Instrucciones compartidas del proyecto

@AGENTS.md
@.engram/CONTEXT.md

Aplicá las reglas y el contexto compartido antes de continuar el rediseño. Trabajá en `feat/panel-admin-redesign`; no mergees a `main` sin autorización nueva y explícita. No publiques credenciales ni registros sensibles de Playwright.

Bases de datos: no crees ni borres bases nuevas.
- Pest corre siempre contra la base fija `mitai_testing`, nunca contra `mitai`: `RefreshDatabase` vacía la base que usa. Si otra sesión está usando `mitai_testing`, esperá o informá el bloqueo.
- La verificación visual con Playwright usa siempre la base de desarrollo `mitai` a través del servidor del puerto 8000. No levantes servidores temporales ni cargues datos sintéticos. Pedí autorización antes de cualquier acción que escriba en `mitai` (pagos, exenciones, altas o cambios).

- Antes de reproducir un recorte en escritorio, verificá y registrá la resolución real de la pantalla, la escala del sistema, el tamaño efectivo de la ventana y del viewport, y el zoom del navegador; reproducí con esa configuración además de los tamaños obligatorios. No confundas una pantalla emulada, `devicePixelRatio` ni `visualViewport.scale` con la resolución física o el porcentaje de zoom; si no podés comprobarlos, consultá al usuario.
- `browser_resize` fija el tamaño de la página (`page.setViewportSize`), no el de la ventana, y queda fijo hasta cerrar el navegador. Para lo que el usuario mira en vivo, no lo uses: si ya lo usaste, ejecutá `browser_close` y volvé a navegar. Verificación y detalles: sección de verificación visual de `AGENTS.md`.
