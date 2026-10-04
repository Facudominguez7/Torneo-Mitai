# Continuidad del rediseño del panel admin

Trabajá siempre en `feat/panel-admin-redesign`, tanto para implementar como para hacer commits y pushes. No mergees este rediseño a `main` sin una autorización nueva y explícita del usuario.

Resumen curado al 2026-10-04. Este archivo viaja con Git; la memoria viva de Engram y la configuración global de cada agente son independientes.

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
