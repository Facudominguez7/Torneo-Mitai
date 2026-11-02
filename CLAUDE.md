# Instrucciones compartidas del proyecto

@AGENTS.md
@.engram/CONTEXT.md

Aplicá las reglas y el contexto compartido antes de continuar el rediseño. Trabajá en `feat/panel-admin-redesign`; no mergees a `main` sin autorización nueva y explícita. No publiques credenciales ni registros sensibles de Playwright.

Bases de datos: no crees ni borres bases nuevas.
- Pest corre siempre contra la base fija `mitai_testing`, nunca contra `mitai`: `RefreshDatabase` vacía la base que usa. Si otra sesión está usando `mitai_testing`, esperá o informá el bloqueo.
- La verificación visual con Playwright usa siempre la base de desarrollo `mitai` a través del servidor del puerto 8000. No levantes servidores temporales ni cargues datos sintéticos. Pedí autorización antes de cualquier acción que escriba en `mitai` (pagos, exenciones, altas o cambios).
