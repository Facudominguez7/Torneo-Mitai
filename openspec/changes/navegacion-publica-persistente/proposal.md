# Propuesta — Navegación pública persistente (issue #97)

## Intención

Agregar a `layouts/navigation.blade.php` los 5 links de sección (`Inicio | Partidos | Posiciones |
Goleadores | Finales`) que hoy no existen en la nav pública, conservando la edición activa al
navegar, resaltando la sección activa y cumpliendo un mínimo de accesibilidad (foco visible,
`aria-current`). Cambio quirúrgico: un partial y un service provider, sin tocar el panel admin ni
la lógica de negocio de las páginas destino.

## Decisiones tomadas (con justificación)

### 1. Resolución de la edición para la nav → view composer, pero scoped al partial

Se registra `View::composer('layouts.navigation', ...)` en `AppServiceProvider::boot()`, **no** uno
por cada vista pública. El composer solo rellena `ultimaEdicion` cuando la vista que incluye la nav
todavía no la trajo (`array_key_exists` sobre `$view->getData()`), con una query barata
(`Edicion::latest('id')->first()`, ya usada en `index()`).

Por qué esto y no un composer por vista pública ni tocar los 6 controladores: el problema real
(explore §2) es que 4 de 6 controladores no pasan `$ultimaEdicion`, así que
`$edicionActiva = $EdicionSeleccionada ?? $ultimaEdicion ?? null` cae a `null` ahí. Componer sobre
el **nombre del partial** (`layouts.navigation`) resuelve el hueco en las 11 vistas que lo incluyen
con un solo punto de registro, sin enumerar vistas públicas a mano (menos superficie, cero riesgo
de olvidarse una). No pisa `$EdicionSeleccionada` (la selección explícita de cada controlador se
respeta tal cual); solo aporta el fallback que falta.

Efecto en `layouts/app.blade.php` (dashboard/perfil): hoy esas páginas no pasan ni
`$EdicionSeleccionada` ni `$ultimaEdicion`, así que la nav ya cae a `null` → "Edición vigente". Con
el composer, van a recibir `$ultimaEdicion` (una query extra, indexada, sin efecto en el resto de la
página) y la nav va a mostrar la última edición real. Es una mejora colateral inocua, no un cambio
de comportamiento de esas páginas.

### 2. Destino de cada link → `idEdicion` explícito, sin tocar controladores destino

Cada link se construye con `route($nombre, ['idEdicion' => optional($edicionActiva)->id])`
(Laravel omite el parámetro si es `null`). Aplica a los 5: `home`, `fixture`, `tabla-posiciones`,
`tabla-goleadores`, `instancias-finales`.

- **Posiciones sin `idCategoria`:** el link de la nav **no** incluye `idCategoria` — hoy no hay una
  categoría "por defecto" evidente por edición y forzar una sería adivinar reglas de negocio fuera
  del alcance de este issue. La página seguirá mostrando "Categoría no encontrada" tal como hoy
  cuando se llega sin categoría; es un problema de UX de esa página, preexistente y fuera del
  alcance de #97 (potencial issue aparte). Lo dejo documentado como riesgo aceptado, no lo arreglo
  acá.
- **Goleadores:** el link lleva `idEdicion`; el controlador hace su PRG habitual (guarda en sesión,
  302 a `tabla-goleadores` sin query). Es el comportamiento existente, no un caso nuevo a manejar.

### 3. Estructura de la nav → fila desktop + fila scrollable en mobile (opción A del explore)

Se agrega una segunda fila de links dentro del mismo `<nav>`, debajo de logo/auth, usando
`flex flex-wrap justify-center gap-x-6 gap-y-2 sm:flex-nowrap` en desktop y
`overflow-x-auto [&::-webkit-scrollbar]:hidden` como salvavalvula en mobile si el ancho no alcanza.

Se descarta bottom nav fijo (opción B): requeriría tocar el `<main>` de los 10 layouts públicos para
compensar el espacio y ya se detectaron layouts con `overflow-x-hidden` + `isolate` + fondos
absolutos (`layouts/fixture.blade.php`) donde un `fixed` interactúa mal con el stacking context. Es
más blast radius (11 archivos tocados en vez de 1) para un mismo resultado funcional. Se descarta
hamburguesa (opción C) porque el issue pide llegar a cada sección en un solo toque.

No se agrega `sticky top-0`: el issue pide "persistente" (presente en toda página, ya es así
estructuralmente por estar en el partial incluido en las 11 vistas), no necesariamente fija al
hacer scroll. Agregar `sticky` es el mismo tipo de riesgo de stacking que el bottom nav, así que lo
dejo fuera de este cambio salvo que el usuario lo pida explícitamente.

**Asunción a confirmar:** la etiqueta del menú es "Partidos" pero la ruta sigue llamándose
`fixture` (no se renombra la ruta: renombrar rutas es un cambio de mayor blast radius, fuera de
alcance). "Vallas" no entra al menú porque el issue no la lista entre las 5 secciones.

### 4. Resaltado de sección activa → mismo idiom que `Panel/admin.blade.php:512-543`

Array plano `['label' => ..., 'ruta' => ..., 'patterns' => [...]]` recorrido con
`request()->routeIs($item['patterns'])`, calculado en un `@php` dentro del propio partial (el
archivo ya usa `@php` inline para `$edicionActiva`/`$homeUrl`; no amerita una clase/View Composer
aparte para 5 líneas de detección). Se reutiliza el idiom que un revisor de este repo ya reconoce.

### 5. Accesibilidad mínima

- `aria-label="Navegación principal"` en el `<nav>`.
- `aria-current="page"` en el link activo.
- Estado activo con contraste real (`text-white` + borde/underline), no solo color, porque el
  inactivo ya es blanco translúcido.
- Foco visible reutilizando el token existente `focus-visible:ring-2 focus-visible:ring-[#f7f280]/40
  focus:outline-none` en los 5 links nuevos. Se extiende el mismo token al link del logo y al de
  "Panel de administración", que el explore marcó sin foco — está dentro del criterio de aceptación
  del issue ("focus states visibles") y es una línea de clases por elemento, no un rediseño.

### 6. Tests → `tests/Feature/NavegacionPublicaTest.php`

Red de seguridad mínima y barata, siguiendo la convención de `tests/Feature/ProfileTest.php` (Pest,
`RefreshDatabase` si aplica, factories existentes):

- Guest en `fixture` con `idEdicion` ve los 5 links, cada uno con `idEdicion` en su `href`.
- El link de "Partidos" tiene `aria-current="page"` cuando la ruta activa es `fixture`; los otros 4
  no lo tienen.
- Usuario autenticado no-admin ve los 5 links + dropdown de perfil, sin "Panel de administración".
- Usuario admin ve además "Panel de administración".
- En una vista que hoy no pasa `$ultimaEdicion` (`tabla-posiciones` sin `idEdicion`), la nav igual
  resuelve una edición vigente para los links (prueba el fallback del composer).

No se agregan tests de las 6 páginas destino en sí (fuera de alcance); solo se ejercen para
verificar que la nav incluida en ellas responde bien.

## No-goals (a confirmar)

- Issues #98, #99, #100, #102, #103, #104: fuera de este change.
- No se rediseña identidad visual existente (logo, botón de login, dropdown de perfil).
- No se toca el header del panel admin (`Panel/admin.blade.php`), que no incluye este partial.
- No se agregan dependencias nuevas (ni Alpine nuevo: la única fila nueva es CSS puro, sin
  `x-data` adicional).
- No se agrega "Vallas" al menú ni se renombra la ruta `fixture` a `partidos`.
- No se arregla el estado "Categoría no encontrada" de Posiciones sin `idCategoria`.

## Alcance de archivos

| Archivo | Cambio | Líneas est. |
|---|---|---|
| `app/Providers/AppServiceProvider.php` | Registrar `View::composer('layouts.navigation', ...)` con fallback de `ultimaEdicion` | +10 |
| `resources/views/layouts/navigation.blade.php` | Array de items de menú + fila de links (desktop/mobile) + `aria-current` + foco en logo/admin | +45 / ~5 modificadas |
| `tests/Feature/NavegacionPublicaTest.php` | Archivo nuevo, 5 casos Pest | +90 |

**Forecast total: ~150 líneas** (umbral de review del proyecto: 400). Cabe en un único PR.

## Riesgos y mitigación

| Riesgo | Mitigación |
|---|---|
| El composer corre en las 11 vistas que incluyen el partial, incluido `layouts/app.blade.php` (dashboard/perfil) | Solo agrega `ultimaEdicion` si no existe; no toca `$EdicionSeleccionada` ni ninguna otra variable de esas páginas. Se valida con smoke test de que dashboard/perfil siguen respondiendo 200. |
| Cero tests previos sobre vistas públicas | Test nuevo cubre los 5 links, `aria-current`, roles guest/auth/admin y el fallback del composer antes de tocar el partial. |
| `tabla-goleadores` con `idEdicion` dispara 302 (PRG) | Comportamiento preexistente; el test para ese link usa `assertRedirect` + follow, no `assertOk` directo. |
| Posiciones sin `idCategoria` sigue mostrando "Categoría no encontrada" al llegar desde la nav | Aceptado como limitación conocida y documentada; no se enmascara ni se falsea una categoría por defecto. |
| Fila de links en mobile podría verse apretada con 5 ítems + logo + auth | Se usa `overflow-x-auto` como salvavalvula, sin fijar altura ni posición (no interactúa con `isolate`/backgrounds de los layouts). |

## Estrategia de verificación

1. `docker compose exec app ./vendor/bin/pint --test`
2. `docker compose exec app php artisan test --compact --filter=NavegacionPublicaTest`
3. Smoke: `docker compose exec app php artisan test --compact --filter=ProfileTest` (confirma que
   tocar el partial compartido no rompe dashboard/perfil).
4. Manual: `npm run dev` (o `composer run dev`) y verificar visualmente foco/`aria-current`/scroll
   mobile en `fixture`, `tabla-posiciones` y `dashboard`, guest y admin.

## Proposal question round

No tuve una ronda interactiva con el usuario final antes de escribir esta propuesta (fase de
proposer, sin acceso directo de chat). Las siguientes asunciones de producto quedaron resueltas con
el criterio de menor blast radius y quedan abiertas a corrección:

1. "Partidos" es la etiqueta visible de la ruta `fixture` — ¿confirmás que no hay que renombrar la
   ruta ni que "Partidos" apunte a otra vista?
2. "Vallas" queda fuera del menú porque el issue no la lista — ¿es intencional o un olvido del
   issue original?
3. La nav no será `sticky` en este change (persistente = presente en toda página, no fija al hacer
   scroll) — ¿es aceptable dejarlo para un change posterior si se quiere sticky?
4. El link de Posiciones no resuelve el caso "sin categoría" — ¿lo dejamos como limitación conocida
   o preferís que abra un issue de seguimiento antes de mergear esto?

Si no hay objeciones, se avanza a Architecture/Task Planner con estas decisiones firmes.
