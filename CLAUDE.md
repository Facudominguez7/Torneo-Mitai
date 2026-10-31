# Instrucciones compartidas del proyecto

@AGENTS.md
@.engram/CONTEXT.md

Aplicá las reglas y el contexto compartido antes de continuar el rediseño. Trabajá en `feat/panel-admin-redesign`; no mergees a `main` sin autorización nueva y explícita. No publiques credenciales ni registros sensibles de Playwright.

Las pruebas que modifican datos usan solo una base de pruebas aislada, nunca desarrollo. Si otra instancia está usando `mitai_testing`, creá y usá otra base de pruebas aislada. Al terminar, incluso si fallan, borrá la base de pruebas utilizada después de verificar su identidad y que ninguna otra sesión la use. Si no podés comprobarlo, no tenés permisos para crear otra base o falla la limpieza, detenete e informá el bloqueo.
