# Continuidad del rediseño del panel admin

Trabajá siempre en `feat/panel-admin-redesign`, tanto para implementar como para hacer commits y pushes. No mergees este rediseño a `main` sin una autorización nueva y explícita del usuario.

Resumen del rediseño al 2026-10-04; checkpoint de cobros actualizado al 2026-10-12; cobros desde la planilla e identidad visual al 2026-10-24. Este archivo viaja con Git; la memoria viva de Engram y la configuración global de cada agente son independientes.

## Goleadores premiados del portal público (rama `feat/home-new`)

- Implementado `design/goleadores-premiados/01-premiados-de-cancha.html` con jugadores, categorías y equipos reales; escudos con respaldo y selector estilizado con alternativa GET sin JavaScript. No se inventan cantidades de goles. Pest: 34 pruebas/267 aserciones incluyendo regresión de Campeones y Subcampeones; Pint en 545 archivos. Playwright comprobó 1920×1080, 1280×720 y 390×844, selector, temas, scroll, hover y movimiento reducido. La base temporal se eliminó al terminar. La evaluación nativa no pudo clasificar por archivos sin seguimiento ajenos; no se obtuvo aprobación nativa.

## Subcampeones del portal público (rama `feat/home-new`)

- Implementado `design/subcampeones/01-podio-de-cancha.html` con datos reales, jerarquía de las cuatro copas, escudos con respaldo y selector estilizado con alternativa GET sin JavaScript. Pest: 36 pruebas/246 aserciones incluyendo regresión de Campeones; Pint en 544 archivos. Playwright comprobó 1920×1080, 1280×720 y 390×844, selector, temas, scroll, hover y movimiento reducido. La base de pruebas temporal se eliminó al terminar. La evaluación nativa no pudo clasificar por archivos sin seguimiento ajenos; no se obtuvo aprobación nativa.

## Campeones del portal público (rama `feat/home-new`)

- Implementado `design/campeones/02-noche-de-campeones.html`: copas y categorías con datos reales, selector de edición, escudos con respaldo y jerarquía visual por copa. Hover respeta movimiento reducido. Pest: 34 pruebas/189 aserciones y Pint en 543 archivos; Playwright comprobó escritorio y móvil, cambio de edición, temas y scroll. La base temporal de pruebas se eliminó al finalizar. El selector de edición usa un menú estilizado con enlaces y alternativa GET sin JavaScript; pruebas focales 5/70 y regresión 25/159 aprobadas, con bases temporales eliminadas. La evaluación nativa no pudo clasificar por archivos sin seguimiento ajenos; no se obtuvo aprobación nativa. Los cambios de Vallas continúan pendientes de cierre y no forman parte de esta verificación.

## Portal público (rama `feat/home-new`)

- Inicio separa partidos y tablas de premios de la edición, conserva la edición en los accesos y usa River Plate como ejemplo. El logo centrado y sin desenfoque se muestra como marca de agua en el sitio público, no en acceso, panel ni impresiones. La navegación de escritorio se compacta con transición e histéresis; en móvil conserva el comportamiento anterior. Pest focal: 43 pruebas/212 aserciones y Pint en 542 archivos. Playwright comprobó portada, finales, fixture, tablas, campeones y baja, tamaños 1920×1080, 1280×720 y 390×844, temas y scroll. La revisión nativa no pudo clasificar por archivos sin seguimiento ajenos; no se comprobó visualmente el perfil autenticado.

## Instancias finales (rama `feat/home-new`)

- Implementado `design/finales/02-camino-copa.html` con datos reales, escudos, filtros de categoría/copa/fase y ganador del partido cuando el marcador o los penales lo determinan. La edición se conserva desde Inicio y no se selecciona en el panel; al elegir un filtro, el panel se cierra. La planilla pública y las variantes previas de tarjeta se conservan. En escritorio las etapas se ordenan de izquierda a derecha y las finales quedan a la derecha; en móvil se apilan. El marcador aparece una sola vez junto a los equipos. El estado vacío distingue edición sin partidos de filtros sin resultados y solo ofrece limpiar cuando hay filtros activos. Pest focal y regresión: 33 pruebas/190 aserciones; Pint y Playwright en escritorio y móvil aprobados. La verificación independiente detectó el caso Gran Final; fue corregido y probado. La evaluación nativa no clasificó por archivos sin seguimiento ajenos.

## Tabla pública de goleadores (rama `feat/home-new`)

- Implementado `design/tabla-goleadores/02-ranking-protagonista.html` con podio para el orden canónico, filtros URL/AJAX, paginación adaptable, tema dual y movimiento reducido. En móvil, el ranking aparece antes y los filtros compactos quedan disponibles desde el inicio; también persisten al desplazar en escritorio y tablet. La pestaña inferior de Goleadores abre esta tabla conservando la edición. Pest focal (22 pruebas, 118 aserciones) y Pint aprobados en Docker; flujos visuales y recuperación de error comprobados con Playwright. La simulación inicial de error no llegó al servidor por el service worker; se verificó luego con un fallo controlado de `fetch` en la página.

## Tabla pública de posiciones (rama `feat/home-new`)

- Implementado el artboard `design/tabla-posiciones/01-tabla-cancha.html`: tabla adaptable con escudos, filtros existentes, temas y estadísticas accesibles en móvil. Verificada en Playwright a 1920×1080, 1280×720 y 390×844; pruebas focales y Pint en Docker aprobados. Los escudos tienen una base clara para mantener el contraste.

## SEO del sitio público (2026-10-25, rama `feat/seo-publico`)

- `config/seo.php` y `SeoComposer` (vista `head`) generan el título, la descripción, la canónica (conserva solo `idEdicion`, `idCategoria`, `idGrupo` y `tipo`), Open Graph/Twitter y robots. El título nombra la edición solo cuando la página la muestra. Sin `idEdicion`, solo `/` e `/instancias-finales` muestran la última edición; el resto muestra el selector y usa `descripcion_general`.
- `/robots.txt` y `/sitemap.xml` son rutas (`ControladorSeo`). Se eliminó `public/robots.txt`. El sitemap lista cada página `por_edicion` una vez por edición, porque el selector no es rastreable. El panel, el acceso, el perfil, las planillas públicas y `email.*` llevan `noindex` y no tienen canónica.
- La imagen para compartir está en `public/og-torneos-mitai.jpg`, porque `/public/fotos` está ignorado por Git. La home tiene JSON-LD (SportsOrganization + WebSite, Posadas/Misiones/AR).
- Los 11 layouts públicos declaran doctype y `lang="es"`: pasaron del modo quirks al modo estándar, con desplazamientos de 6 a 16 px revisados visualmente.
- Blade interpreta `<?xml` como apertura de PHP: la declaración XML se arma en el controlador.
- Después del deploy hay que cargar el sitemap en Search Console.

## Continuidad previa (2026-10-24): cobros desde la planilla e identidad visual

- Pagos desde la planilla del partido: badges en el dashboard, pago combinado efectivo + Mercado Pago (sin transferencia), impresión en el lugar y hoja A4 vertical. El estado se refresca por polling cada 1 s mientras la pestaña está visible; se descartó websockets porque no hay broadcasting configurado.
- Exención de planilla desde el partido (ese cargo) y desde `/Panel/equipo` (toda la edición: política futura + deuda actual, con los pagos conservados). `eximirEdicion` toma primero el lock global de configuración de cobros para no interbloquearse. Se quitó la "Planilla de Cobro" impresa.
- Nombres de jugadores (rama `feat/jugadores-nombre-mayuscula`): el modelo `Jugador` guarda nombre y apellido con la inicial de cada palabra en mayúscula y el resto en minúscula (`Str::title`). La migración `2026_10_24_200000_capitalize_jugadores_nombres` corrige los existentes y es irreversible. En `mitai` local cambia 186 de 3317 (probado con rollback, todavía sin aplicar).
- Acceso "Cancha nocturna" (rama `feat/acceso-cancha-nocturna`, diseño aprobado en Claude Design): login, registro, recuperar/restablecer/confirmar contraseña y verificar email comparten `layouts/guest` y los componentes `x-acceso.*` (el perfil sigue con `x-text-input`). Tokens `brand-navy-deep` y `brand-teal-light`. Un 419 en el login vuelve con el aviso "La sesión expiró. Volvé a intentar." o lleva al destino si ya ingresó. Páginas `/terminos-y-condiciones` y `/politica-de-privacidad` (borrador pendiente de revisión legal). El registro exige aceptar y guarda `users.terminos_aceptados_at` (fuera de `$fillable`); el alta con Google también. Migración aplicada en `mitai` con autorización; la de nombres sigue pendiente.
- Tests: el contenedor tiene `APP_ENV=local`, que pisa `phpunit.xml`; por eso los tests verifican CSRF y hay 23 fallas 419 preexistentes.
- Identidad visual: AGENTS.md sección 10 (evitar AI-slop). Barlow es la tipografía. No quedan clases `indigo-*` (`PaletaMarcaTest`). Las pantallas de importar equipos de otra edición usan los componentes `x-ui.*`. El dashboard saluda según la hora.
- En `mitai` local, con autorización del usuario, hay dos pagos reales del partido 2213 (Huracán). No se confirmó ninguna exención real.
- Pendiente: resplandores `blur-3xl` del sitio público (decisión del usuario) y el buscador al lado de "Mitai Admin", que funciona como Consulta de jugador. La revisión nativa de cobros-planilla-partido sigue bloqueada. Hay 23 fallas 419 preexistentes en los tests de Auth/Profile.

## Continuidad previa: interfaz de cobros integrada

- Habilitación de desarrollo autorizada para el puerto 8000: se aplicaron únicamente las diez migraciones pendientes de cobros en `mitai`, con whitelist y guardia de base; todas terminaron correctamente. No se ejecutaron activación, seeders, backfill, pagos ni cambios de cuentas. La ruta `/Panel/cobros` responde y redirige al login. La comprobación SQL independiente posterior falló con `QueryException` sin resultados parciales: no prueba divergencia, pero falta confirmar el estado final. No reintentar ni operar sobre datos automáticamente; queda una decisión humana para diagnóstico de lectura. Seguimiento: `odd/tasks/cobros-development-8000.md`.

- La consulta, pagos, crédito automático, exenciones, políticas futuras, configuración manual y detalle/historial están conectados al panel. Acceso desde menú y tarjetas de partidos; tabla desktop, tarjetas mobile y modales sin apilar; impresión filtrada.
- Las altas regulares, interzonales y finales usan el motor transaccional existente. Inactivo no genera cargos; activar exige confirmación; no hay backfill. La tarifa histórica y los ingresos se conservan. No hay anulación de pagos implementada.
- Verificación independiente: focal 55/458 y regresión 561 aprobadas con un fallo de aserción CSS global, corregido y revalidado con 1 test/13 aserciones; Pint y sintaxis JS pasan. No se ejecutó suite completa ni contención real.
- Playwright MCP con Chrome visible: 1920×1080, 1280×720 y 390×844, sin overflow; teclado/foco/scroll, pagos parciales/excedentes, recuperación del mismo UUID tras respuesta perdida/recarga/500, motivos literales, exenciones, política futura, tarifa histórica, filtros, impresión y altas antes/después de activar comprobados. Capturas revisadas en storage/framework/testing/cobros-panel-browser (no publicar perfil ni datos de sesión).
- Durante la verificación inicial de implementación, todas las escrituras y activaciones fueron sintéticas en mitai_testing, confirmada mediante SELECT DATABASE(); todavía no se había migrado ni activado desarrollo. Dos intentos auxiliares fallaron por timeout (captura adicional y selector main inexistente); no se contaron como aprobados. La captura de detalle móvil y los flujos funcionales sí se completaron.
- La evaluación nativa no pudo determinar reviewDue por archivos sin seguimiento ajenos; no afirmar aprobación nativa de estas unidades. No hay push ni merge autorizados. Seguimiento local: odd/tasks/cobros-panel.md.

## Continuidad previa: backend de cobros y exenciones

- Rama actual `feat/panel-admin-redesign`; remoto integrado sin conflictos por merge `3e5c6f1`. Los cambios locales ajenos se conservaron.
- Engram local recibió 13 chunks remotos (453 observaciones). Para otra máquina: `engram sync --import`, sin exportar automáticamente conversaciones.
- E1–E3 completos: persistencia de exenciones, cálculo de deuda que las contempla y operación transaccional de eximir un cargo de un equipo. E4A (`8d96d8e`) guarda políticas históricas equipo-edición, todavía sin materialización automática.
- E4B implementado y verificado: `ExencionesCobroService::registrarPolitica`, administrador vigente, configuración existente activa, inscripción equipo-edición, rechazo de duplicados y política+historial atómicos. Motivo opcional literal; conserva pagos y deudas anteriores. Verificación independiente S1–S3 PASS; revisión nativa de los dos archivos aprobada y acknowledgement completado (`review-b1626f69c6392cc6`). El commit de esta unidad incluye servicio, tests y este contexto, con autorización explícita del usuario.
- Evidencia E4B propia e independiente: RED observado por el padre; GREEN focalizado 19 tests / 101 aserciones y regresión de cobros 528 tests / 1822 aserciones; Pint de los dos archivos pasa. No se ejecutó suite completa, UI ni carreras reales. Advertencias: versión Compose obsoleta y caché Pest sin permiso de escritura.
- E4C materialización implementada y verificada: exención plena al crear cargos nuevos de equipos con política vigente, regular/interzonal/final. Nueva migración `2026_10_12_124733_add_politica_id_to_exencion_cobro_planillas_table` enlaza cada exención con su política (FK restrictiva, nullable para legado). Evidencia: RED observado, GREEN 22/266, regresión de cobros 550 tests / 2088 aserciones, Pint de los cuatro archivos pasa. Verificación independiente S1–S4 PASS sin bloqueantes; revisión nativa riesgo+confiabilidad aprobada y acknowledgement consumido (`review-b1626f69c6392cc6`). 218 líneas. Sin backfill ni deduplicación; sin HTTP/UI, activación ni migración de la base de desarrollo. Commit completado en dfb2cc2; push no autorizado.
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
