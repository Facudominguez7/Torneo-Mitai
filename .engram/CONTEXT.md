# Continuidad del rediseño del panel admin

Trabajá siempre en `feat/panel-admin-redesign`, tanto para implementar como para hacer commits y pushes. No mergees este rediseño a `main` sin una autorización nueva y explícita del usuario.

Resumen del rediseño al 2026-10-04; checkpoint de cobros actualizado al 2026-10-12. Este archivo viaja con Git; la memoria viva de Engram y la configuración global de cada agente son independientes.

## Continuidad actual: cobros y exenciones

- Rama actual `feat/panel-admin-redesign`; remoto integrado sin conflictos por merge `3e5c6f1`. Los cambios locales ajenos se conservaron.
- Engram local recibió 13 chunks remotos (453 observaciones). Para otra máquina: `engram sync --import`, sin exportar automáticamente conversaciones.
- E1–E3 completos: persistencia de exenciones, cálculo de deuda que las contempla y operación transaccional de eximir un cargo de un equipo. E4A (`8d96d8e`) guarda políticas históricas equipo-edición, todavía sin materialización automática.
- E4B implementado y verificado: `ExencionesCobroService::registrarPolitica`, administrador vigente, configuración existente activa, inscripción equipo-edición, rechazo de duplicados y política+historial atómicos. Motivo opcional literal; conserva pagos y deudas anteriores. Verificación independiente S1–S3 PASS; revisión nativa de los dos archivos aprobada y acknowledgement completado (`review-b1626f69c6392cc6`). El commit de esta unidad incluye servicio, tests y este contexto, con autorización explícita del usuario.
- Evidencia E4B propia e independiente: RED observado por el padre; GREEN focalizado 19 tests / 101 aserciones y regresión de cobros 528 tests / 1822 aserciones; Pint de los dos archivos pasa. No se ejecutó suite completa, UI ni carreras reales. Advertencias: versión Compose obsoleta y caché Pest sin permiso de escritura.
- E4C materialización implementada y verificada: exención plena al crear cargos nuevos de equipos con política vigente, regular/interzonal/final. Nueva migración `2026_10_12_124733_add_politica_id_to_exencion_cobro_planillas_table` enlaza cada exención con su política (FK restrictiva, nullable para legado). Evidencia: RED observado, GREEN 22/266, regresión de cobros 550 tests / 2088 aserciones, Pint de los cuatro archivos pasa. Verificación independiente S1–S4 PASS sin bloqueantes; revisión nativa riesgo+confiabilidad aprobada y acknowledgement consumido (`review-b1626f69c6392cc6`). 218 líneas. Sin backfill ni deduplicación; sin HTTP/UI, activación ni migración de la base de desarrollo. Commit pendiente de autorización; push no autorizado.
- La sección de reemplazo de equipo siguiente es contexto histórico, no el foco activo.

## Retomar el trabajo

1. Leé `AGENTS.md` y este archivo antes de modificar el proyecto.
2. Confirmá rama, `git status` y último commit: el estado real de Git prevalece sobre este resumen.
3. Consultá Engram para recuperar decisiones y evidencia. Si existe `odd/tasks/panel-admin-redesign.md`, reconciliá su estado; ese documento es local y está ignorado por Git.
4. No publiques conversaciones, contraseñas, hashes, cookies, tokens ni capturas de pantallas de error que expongan datos sensibles.

## Estado del rediseño

- T1–T11 figuran completas en el documento local: shell, dashboard, componentes, módulos del torneo, equipos y jugadores, estadísticas, comunicación e impresiones.
- T11 incorporó los cambios de `main` sobre reemplazo de equipo y detalle de equipos, adaptados al nuevo panel. Su commit de integración es `245dd8f`.
- La rama de integración recibió ese historial por fast-forward, sin modificar `main`.
- El commit `e20815f` incorpora la skill UI/UX y las configuraciones de herramientas existentes. No implementa las mejoras UX pendientes.
- Ya no se usa la estrategia de ramas por tarea: el usuario indicó que todo debe quedar en `feat/panel-admin-redesign`. Antes de borrar ramas remotas antiguas, comprobá que sus commits estén contenidos en la rama de integración.

## Herramientas y restricciones

| Tema | Estado / regla |
| --- | --- |
| Stack | Laravel 11, PHP 8.3, Blade, Alpine y Tailwind 3. |
| UI/UX | `ui-ux-pro-max` está en `.agents/skills/ui-ux-pro-max`; Claude usa un enlace relativo en `.claude/skills`. Pi descubre `.agents/skills`. |
| Versiones de la skill | Sus recomendaciones pueden apuntar a Laravel 13 / Tailwind 4: no trasladar esos valores al proyecto. El diseño aprobado y las reglas locales tienen prioridad. |
| MCP de Pi | Context7 global y Laravel Boost local vía Docker fueron conectados y verificados en esta máquina. `.pi/` está ignorado: otra máquina debe configurarlos de nuevo. |
| PHP | Ejecutar comandos relacionados con el proyecto dentro del contenedor `app`. |
| Datos | No alterar la base de desarrollo, reemplazar equipos ni cambiar permisos de cuentas sin autorización específica. |
| Secretos | Credenciales únicamente en configuración local ignorada; nunca en este resumen, commits o exportaciones de conversaciones. |

## Próximo foco: reemplazar equipo

La revisión actual es de lectura y propuesta, no una autorización para implementar los cambios sugeridos.

- Entrada actual: tabla de posiciones. Evaluar un acceso contextual desde el detalle del equipo.
- Mostrar únicamente los campos del origen elegido para reducir ruido.
- Presentar un resumen claro de equipo saliente, reemplazante y consecuencias antes de confirmar.
- Mantener visible el reemplazante elegido al cambiar una búsqueda; revisar navegación por teclado y agrupación nativa de radios.
- Archivo principal: `resources/views/Panel/reemplazo-equipo/create.blade.php`.
- No confirmar ningún reemplazo real durante una auditoría visual.

### Acceso visual pendiente

El hash de una cuenta de prueba local se corrigió con autorización específica y se verificó como bcrypt. Esa cuenta todavía no tiene rol admin y exige restablecer la contraseña; no se modificaron esos atributos. El ingreso autenticado al panel y su configuración compartida entre agentes siguen pendientes de resolver con autorización específica. Las credenciales se omiten deliberadamente.

## Verificaciones y límites conocidos

- JSON de `opencode.json`, `skills-lock.json` y `.claude/settings.json`: válido.
- Sintaxis del hook de Claude: pasa; cinco casos por stdin pasan. El hook es heurístico, no una barrera de seguridad completa.
- Skill: 137 tests autocontenidos pasan, también verificados independientemente con temporales aislados dentro de `.pi/verification-temp`.
- Descubrimiento completo upstream: cuatro módulos de tests no cargan porque buscan archivos del repositorio original de la skill. No se borraron ni modificaron para ocultar esos errores.
- `git diff --check` señaló espacios finales en líneas vacías del archivo upstream `scripts/design_system.py`; se conservó el contenido vendorizado.
- La revisión nativa del candidato de herramientas fue declinada por el usuario; hubo verificación independiente. No afirmar que ese candidato tiene una aprobación nativa.
- No se reejecutó la suite Laravel ni se completó una revisión visual admin en esta sesión. Para nuevos cambios UI, usar Pest/Pint en Docker y Playwright a 1280 y 390 px, incluyendo teclado y scroll.

## Mantener este contexto

Actualizá este resumen y Engram al cerrar cambios o decisiones relevantes. Los chunks históricos de `.engram/` no equivalen a este resumen actualizado. No exportes automáticamente toda la memoria o los prompts a Git: primero verificá que no contengan datos privados.
