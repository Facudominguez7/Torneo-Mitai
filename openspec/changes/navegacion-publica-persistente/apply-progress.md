# Apply Progress — navegacion-publica-persistente

## Estado: implementación T1–T4 completa (GREEN). T5 (manual visual) pendiente del usuario.

## TDD Cycle Evidence (Strict TDD)

| Ciclo | Comando | Resultado | Evidencia |
|---|---|---|---|
| RED (T1) | `docker compose exec app ./vendor/bin/pest tests/Feature/NavegacionPublicaTest.php` | **5 failed, 3 passed (16 assertions)** | Fallos SOLO por asserts de contenido: "muestra los cinco links…" (links ausentes), "resuelve por fallback…" (`InvalidExpectationValue`: link `null`), "marca con aria-current solo partidos…" (`0 is identical to 1`: cero `aria-current` en la nav), "marca solo el link de inicio…" (link `null`), "usa la edicion explicita…" (`InvalidExpectationValue`: link `null`). Los 3 que pasan en RED son guardas de comportamiento existente (200, roles, idCategoria). **Cero errores 500.** |
| GREEN (T2) | idem | 5 failed, 3 passed (sin regresión: el composer solo aporta `ultimaEdicion`; el partial aún no renderiza links) | Composer `NavigationComposer` + registro `View::composer('layouts.navigation', …)` con guard `array_key_exists`. Sin 500 en las 11 vistas que incluyen el partial. |
| GREEN (T3) | idem | **8 passed (40 assertions)** | Fila de 5 links + `aria-current` en el partial; todos los asserts verdes. |
| REFACTOR (T4) | `docker compose exec app ./vendor/bin/pint --test app/Providers/AppServiceProvider.php app/View/Composers/NavigationComposer.php tests/Feature/NavegacionPublicaTest.php` | **PASS (3 files)** | Re-run de tests post-formato: **8 passed (40 assertions)** — cero cambios funcionales. |

## Comandos de verificación ejecutados (todos en Docker)

1. `docker compose exec app ./vendor/bin/pest tests/Feature/NavegacionPublicaTest.php` → **8/8 passed (40 assertions)**.
2. `docker compose exec app ./vendor/bin/pest --filter="ProfileTest"` → **4 failed, 1 passed — PREEXISTENTE en la branch**. Verificado con `git stash push -u` + re-run sin mis cambios: **mismo resultado 4 failed / 1 passed**. Los 4 fallos son `419 CSRF` en POST/PATCH/DELETE de perfil (`profile information can be updated`, `email verification status…`, `user can delete their account`, `correct password…`), ajenos a este change. El caso que ejercita la nav compartida (`profile page is displayed`) pasa con y sin mis cambios.
3. `docker compose exec app ./vendor/bin/pest --filter="NavegacionPublicaTest|ProfileTest"` → 4 failed, 9 passed (los 4 fallos = los preexistentes de ProfileTest; 8 Navegacion + 1 profile-page = 9 passed).
4. `docker compose exec app ./vendor/bin/pint --test <mis 3 archivos>` → PASS. Nota: `--dirty` no está disponible en el contenedor ("only available when using Git", el mount no tiene .git); se usaron rutas explícitas.
5. `php -l app/Providers/AppServiceProvider.php` → No syntax errors.
6. `git diff --stat` / `git diff -w --stat` → ver abajo (misma forma con y sin `-w` en el partial: cero ruido de whitespace).

## Archivos creados / modificados (solo superficies permitidas)

| Archivo | Cambio | Líneas |
|---|---|---|
| `tests/Feature/NavegacionPublicaTest.php` | NUEVO — 8 casos Pest + 3 helpers (`navPublica`, `linkDeSeccion`, `crearEdicionDePrueba`) | 133 |
| `app/View/Composers/NavigationComposer.php` | NUEVO — fallback `ultimaEdicion` con guard `array_key_exists` | 18 |
| `app/Providers/AppServiceProvider.php` | Registro `View::composer('layouts.navigation', …)` (+6) + `use` (+1); además fixes Pint mecánicos de código preexistente (imports FQCN→alias, imports sin usar, `if(`→`if (`, `!$x`→`! $x`, docblock) | +25 / −14 (−w: +22 / −11) |
| `resources/views/layouts/navigation.blade.php` | `aria-label` en `<nav>` (1 línea tocada), foco visible en logo y "Panel de administración" (2 líneas tocadas), fila de 5 links con `aria-current`/`routeIs` patterns exactos, mobile `overflow-x-auto`, Tailwind v3 | +29 / −3 (idéntico con `-w`) |

### Diff stats

```
git diff --numstat  (solo mis archivos):
25  14  app/Providers/AppServiceProvider.php
29   3  resources/views/layouts/navigation.blade.php

git diff -w --numstat:
22  11  app/Providers/AppServiceProvider.php
29   3  resources/views/layouts/navigation.blade.php

Nuevos: NavigationComposer.php 18 líneas, NavegacionPublicaTest.php 133 líneas.
Total autorado ≈ 205 líneas < 400 (budget). Los archivos ajenos modificados
(.gitignore, opencode.json, sync-db.sh) NO fueron tocados: son modificaciones
preexistentes en el working tree.
```

## Desvíos respecto a las instrucciones del parent (justificados)

1. **Caso de test 5 ajustado al spec:** el parent pedía "en `route('home')` NINGÚN link de sección tiene aria-current", pero el spec R3 ("El enlace correspondiente a la ruta actualmente activa MUST marcarse con `aria-current="page"`") obliga a marcar **Inicio** cuando la ruta activa ES home; tasks.md T3 implementa `routeIs(patterns)` para los 5 items sin excepción. El test quedó como guarda spec-compatible: en home `aria-current="page"` aparece EXACTAMENTE 1 vez y es sobre el link Inicio; los otros 4 no lo llevan. Documentado como contradicción test↔spec resuelta a favor del spec (regla del parent: "si el test contradiga el spec, documentalo").
2. **Link de registro NO asertado:** el spec R4 (scenario "Invitado ve opciones de acceso") menciona login **y registro**, pero el partial hoy solo tiene "Iniciar sesión" y T3 prohíbe tocar el bloque de auth. Agregar un link de registro quedaría fuera de las superficies y del alcance del issue #97 (nav de secciones). Queda documentado como gap conocido, no enmascarado en el test.
3. **`href` usa `$edicionActiva`, no `$ultimaEdicion`:** el snippet del parent decía `route($item['ruta'], ['idEdicion' => optional($ultimaEdicion)->id])`, pero eso rompería el caso 6 (edición explícita del controlador debe ganar) y el spec R2 escenario 2. Se usó `optional($edicionActiva)->id`, donde `$edicionActiva = $EdicionSeleccionada ?? $ultimaEdicion ?? null` (ya definido en el partial). Verificado en GREEN.
4. **`pint --dirty` indisponible en el contenedor** (mount sin git): se corrió `pint --test` con rutas explícitas de los 3 archivos nuevos/tocados. `sync-db.sh` y demás archivos ajenos: NO tocados ni pintados.
5. **Fixes Pint sobre código preexistente en `AppServiceProvider.php`:** el archivo ya fallaba `pint --test` antes de este change (imports FQCN inline, `Lang`/`Event` sin usar, `if(` sin espacio, `!$var` sin espacio, `@return void`). Como T4 exige Pint OK en archivos tocados y el riesgo de budget es Low, se aplicó `pint` sobre ESE archivo único (diff mecánico, sin cambios funcionales; tests re-verde después).

## Tareas completadas (checkboxes en tasks.md)

- [x] T1 — RED: `tests/Feature/NavegacionPublicaTest.php` (8 casos, falla por asserts, sin 500)
- [x] T2 — GREEN: composer en `AppServiceProvider` + `NavigationComposer` (guard `array_key_exists`, no pisa `EdicionSeleccionada` ni `Panel.admin`)
- [x] T3 — GREEN: fila de links en `layouts/navigation.blade.php` (5 links, patterns exactos sin wildcard, `aria-current`, foco visible, 8/8)
- [x] T4 — Formato y regresión: Pint PASS en los 3 archivos, Navegacion 8/8, ProfileTest sin regresiones nuevas (fallos preexistentes documentados)

## Tarea restante (no automatizable)

- [ ] T5 — Verificación manual (visual / mobile). Checklist para el usuario, con `npm run dev`:

  1. **Scroll mobile:** en viewport angosto (<640px) recorrer `fixture`, `tabla-posiciones` y `home`: los 5 links visibles/accesibles, la página SIN scroll horizontal de página (el único scroll admitido es dentro del contenedor de la nav, con scrollbar oculto).
  2. **Foco con teclado:** con Tab recorrer logo, "Panel de administración" (siendo admin) y los 5 links: anillo visible (`focus-visible:ring-[#f7f280]/40`) en todos.
  3. **Contraste del activo:** en `fixture` y `tabla-posiciones` el link activo se distingue por borde inferior `#f7f280` + `text-white` (no solo color translúcido); verificar `aria-current="page"` en el DOM.
  4. **Panel admin visualmente intacto:** `Panel/admin` no incluye este partial — verificar que el header del panel no cambió.
  5. **Dashboard/perfil con la nav nueva:** como autenticado no-admin, verificar `dashboard` y `profile` renderizan la nav con la edición vigente real (antes mostraba "Edición vigente"; ahora el composer aporta la última edición) y sin errores.

## Estado SDD consumido / producido

- Status nativo marcaba el change como ambiguo (3 changes candidatas); el parent resolvió la selección explícitamente (`navegacion-publica-persistente`) → se procedió según prompt del orquestador.
- `actionContext.mode: repo-local`, `allowedEditRoots: [/home/facundo/Documentos/Torneo-Mitai-Laravel]` → todos los archivos editados dentro del root. Sin warnings.
- Gate de carga de trabajo: `Decision needed before apply: No`, `Chained PRs: No`, `400-line budget risk: Low` → single PR, sin decisión de delivery requerida.
- No se hizo commit ni push (restricción del parent).

## Ronda 2 — barra sticky de secciones, overflow-x en roots, defaults de posiciones

### Ajustes implementados

1. **Barra de secciones sticky separada** (`resources/views/layouts/navigation.blade.php`): la fila de secciones (`$itemsSeccion` + foreach) se movió FUERA del `<nav>` principal a una barra hermana `<nav aria-label="Secciones del torneo" class="sticky top-0 z-50 border-b border-white/10 bg-black/40 backdrop-blur">`. Se agregó auto-scroll horizontal del link activo (`scrollIntoView`, inline center) vía `<script>` antes del cierre de la barra nueva. Touch targets: `py-1.5` → `py-2` en los links. El contenido interno del bloque movido quedó byte-idéntico salvo `py-2` (verificado: `git diff` vs `git diff -w` dan los mismos números para el archivo).
2. **Sticky requiere ancestros sin `overflow-hidden`**: se cambió el contenedor raíz de página de `overflow-hidden` a `overflow-x-hidden` (preserva el clip horizontal de los fondos decorativos absolutos) en: campeones, goleadores, instancias-finales, subcampeones, tabla-goleadores, tabla-posiciones, tabla-vallas, vallas (8 layouts, 1 línea cada uno, solo el root). **Sin cambios**: `fixture.blade.php` (su root ya era `overflow-x-hidden`) y `welcome.blade.php` (la nav es hija directa de `<body>`; sus `overflow-hidden` son `<section>` internos que NO son ancestros de la nav).
3. **Posiciones sin `idCategoria` ya no cae en "Categoría no encontrada"** (`ControladorHome::tablaPosiciones`, solo ese método): bloque de defaults determinísticos tras `$EdicionSeleccionada = ...` — primera categoría de la edición (`orderBy nombreCategoria desc`) y su primer grupo (`orderBy id`). `$GrupoSeleccionado` y el cálculo de `$categoria`/`$nombreCategoria` quedaron después del bloque para consumir los valores resueltos. El resto del método (query, view compact) intacto. Sin `idEdicion` el comportamiento legacy queda igual.
4. **Test de regresión** `tests/Feature/TablaPosicionesDefaultTest.php` (Pest, 3 casos).

### TDD evidence (test de posiciones)

| Fase | Evidencia |
|---|---|
| RED | `docker compose exec app ./vendor/bin/pest tests/Feature/TablaPosicionesDefaultTest.php` → **2 failed, 1 passed**: caso default falla en `toContain('Sub 17')` (título cae en el fallback y tabla vacía sin defaults); caso con `idCategoria` explícita falla en `toContain('Equipo Sur')` (título correcto pero tabla vacía sin `idGrupo`); caso legacy sin `idEdicion` pasa (200). Nota de fixture: `golesContra`, `ganado`, `perdido` y `empatado` son NOT NULL sin default en `tabla_posiciones`; se completaron en el fixture antes del RED de comportamiento. |
| GREEN | Mismo comando tras el fix → **3 passed (10 assertions)**. |
| TRIANGULATE | Caso 2 cubre que el default de grupo respeta la categoría explícita (no cae al primer grupo de la primera categoría: `not->toContain('Sub 17')`); caso 3 cubre legacy sin `idEdicion`. |

### Bloqueo del helper de test y resolución autorizada

- **Bloqueo:** con la barra sticky fuera del nav principal, el helper `navPublica()` de `tests/Feature/NavegacionPublicaTest.php` (regex lazy `<nav\b.*?</nav>`, capturaba solo la PRIMERA nav) dejó de ver los links de sección → 5 tests en rojo. Ese archivo estaba fuera de las superficies de la Ronda 2 → se escaló al orquestador con opciones cerradas.
- **Resolución autorizada (Opción 2):** `preg_match_all('/<nav\b.*?<\/nav>/s', ...)` + `implode("\n", $coincidencias[0])` — captura la barra principal + la sticky de secciones en cualquier página, sin asumir cuántas navs hay.
- **Ajuste adicional del test (documentado, autorizado por la regla "corregí el TEST solo si contradice el comportamiento implementado"):** los 2 tests que contaban `substr_count($nav, 'aria-current="page"') === 1` pasaron a contar 2 porque el `<script>` de auto-scroll (especificado literalmente en el AJUSTE 1) contiene el literal del selector `a[aria-current="page"]`. El comportamiento es correcto (un solo link activo); el proxy del test quedó desactualizado. Fix: contar el atributo solo dentro de tags de ancla: `preg_match_all('/<a\b[^>]*aria-current="page"/', $nav))->toBe(1)`. Ningún assert semántico cambió de intención: sigue exigiendo exactamente UN link activo por página.

### Validaciones ejecutadas (todas en Docker)

1. `docker compose exec app ./vendor/bin/pest tests/Feature/NavegacionPublicaTest.php tests/Feature/TablaPosicionesDefaultTest.php` → **11 passed, 0 failed (50 assertions)** = 8 (Navegación) + 3 (Defaults).
2. `docker compose exec app ./vendor/bin/pest --filter="ProfileTest"` → **4 failed, 1 passed (8 assertions)** — idéntico al baseline preexistente (4×419 CSRF), sin regresiones nuevas.
3. `docker compose exec app ./vendor/bin/pint --test app/Http/Controllers/ControladorHome.php tests/Feature/TablaPosicionesDefaultTest.php` → "2 files, 1 style issue": falla SOLO `ControladorHome` por la deuda preexistente de imports (`new_with_parentheses, fully_qual…`, idéntica antes y después del cambio); el test nuevo limpio. Adicional: `pint --test tests/Feature/NavegacionPublicaTest.php` → PASS (archivo tocado con autorización).
4. `git diff --stat` vs `git diff -w --stat` → idénticos en TODOS los archivos de la Ronda 2 (navigation 43, ControladorHome 23, 8 layouts 2±1); cero ruido de whitespace. Las únicas diferencias globales están en `opencode.json` y `sync-db.sh` (archivos ajenos preexistentes, no tocados).
5. Curl de humo (id real vía tinker, `idEdicion=10`):
   - `GET /tabla-de-posiciones?idEdicion=10` → **200**; `grep -c "Categoría no encontrada"` = **0**; título renderizado: "Tabla de posiciones · Categoría 2019" (primera categoría por `nombreCategoria desc`).
   - `GET /tabla-de-goleadores` → `grep -c 'sticky top-0'` = **1** (≥ 1 requerido).
6. Archivos ajenos intactos: `.gitignore`, `opencode.json`, `sync-db.sh` sin cambios en esta ronda (sus diffs son preexistentes del working tree). `app/Providers/AppServiceProvider.php` tampoco se tocó en la Ronda 2.

### Checklist manual (sticky/mobile) — para el usuario, con `npm run dev`

1. **Barra fija al scrollear:** en cada página pública (home, fixture, tabla-de-posiciones, tabla-de-goleadores, instancias-finales, campeones, subcampeones, goleadores, vallas, tabla-de-vallas) scrollear hacia abajo y verificar que la barra de secciones queda fija arriba (`sticky top-0`), debajo del header, con fondo translúcido + blur y borde inferior.
2. **Auto-scroll del activo:** en un viewport angosto, entrar a una sección cuyo link activo quede fuera de la vista (muchas secciones); al cargar, el link activo debe auto-centrarse horizontalmente (`scrollIntoView` smooth) sin scroll vertical de página.
3. **Scroll horizontal mobile:** en viewport < 640px recorrer las 10 páginas: SIN scroll horizontal de página completa; el único scroll horizontal admitido es dentro de la barra de secciones (scrollbar oculto). Los fondos decorativos absolutos no deben generar overflow (por eso `overflow-x-hidden` solo en el root).
4. **Sticky realmente pega:** confirmar que ninguna página volvió a tapar la barra (si un ancestro con `overflow-hidden` apareciera, la barra no pegaría; hoy los 10 roots están saneados).
5. **Foco con teclado:** con Tab recorrer logo, acciones de auth y los 5 links de sección: anillo visible en todos (incluidos los de la barra sticky, que mantiene el `focus-visible:ring`).
6. **Posiciones con defaults:** entrar a `Tabla de posiciones` desde la nav (sin `idCategoria`): debe mostrar la primera categoría (mayor `nombreCategoria`) y la tabla de su primer grupo; con `idCategoria` explícita debe respetarse esa categoría; sin `idEdicion` todo sigue como antes.
7. **Panel/dashboard/perfil intactos:** `Panel.admin` no incluye este partial; verificar que el header del panel no cambió y que `dashboard`/`profile` renderizan la nav doble sin errores.

Estado de la Ronda 2: implementación y validación automatizada completas. Sin commits (restricción del parent).

## Ronda 3 — sticky real en páginas públicas + switcher de categorías/grupos en Posiciones

### Causa raíz del sticky (verificada)

La Ronda 2 puso `overflow-x-hidden` en el root de 8 layouts para recortar los fondos decorativos absolutos. En CSS, un valor distinto de `visible` en `overflow-x` computa `overflow-y: auto` (spec: si un eje es `visible` y el otro no, el `visible` se computa `auto`): el root pasaba a ser **scroll container**, la barra `sticky top-0` se anclaba a ese contenedor (que no scrollea, scrollea el viewport) y nunca pegaba al viewport. En `welcome` la nav es hija directa de `<body>` y por eso sí funcionaba.

### Fix (AJUSTE A)

- Root de 9 layouts (los 8 de la Ronda 2 + `fixture`, que ya venía con `overflow-x-hidden` de una sesión anterior): se quitó `overflow-x-hidden` (`relative isolate min-h-screen` queda). Root sin overflow → el sticky vuelve a anclarse al viewport.
- Wrapper del fondo decorativo `<div class="absolute inset-0">`: se agregó `overflow-hidden` para que el recorte visual del fondo viva ahí. Wrapper **presente en los 9/9 layouts** (ninguno carecía de él; no se inventó estructura). `welcome.blade.php`: NO tocado. `guest.blade.php`: NO tocado (fuera de superficies; ya usaba este mismo patrón root sin overflow-x + wrapper con overflow).

### Switcher de categorías y grupos (AJUSTE B)

- `ControladorHome::tablaPosiciones` (solo ese método): tras el bloque de defaults de la Ronda 2 se cargan `$categorias` (de la edición, `nombreCategoria desc`) y `$grupos` (de la categoría activa, `id` asc), con `collect()` vacíos como fallback (sin edición el view recibe collections vacías). `Categoria` y `Grupo` ya estaban importados (verificado por grep, no se agregaron `use`). `'categorias'` y `'grupos'` agregados al `compact(...)` final.
- `resources/views/layouts/tabla-posiciones.blade.php`: se eliminó el bloque "Volver al fixture" (`@if` + `<a>` a `route('fixture', ...)`). En su lugar, debajo del título/descripción: fila horizontal scrollable de chips de categorías (`overflow-x-auto` + `whitespace-nowrap` + targets `py-2`), chip a `route('tabla-posiciones', ['idEdicion' => ..., 'idCategoria' => $c->id])` sin `idGrupo` (el controller resuelve el primer grupo). Chip activo (`$c->id == $idCategoria`): `border-[#f7f280] bg-[#f7f280]/20 text-[#f7f280]` + `aria-current="page"`; inactivo blanco suave; `focus-visible:ring-[#f7f280]/40`. Segunda fila de grupos SOLO si `$grupos->count() > 1`, misma estética, href con `idGrupo`, activo `$g->id == $idGrupo`. Ambas filas envueltas en `<div role="group">` con `aria-label="Cambiar categoría"` / `"Cambiar grupo"` (filtros, no nav de sitio). Nada más de la vista fue tocado.

### Test (AJUSTE C) — TDD

| Fase | Evidencia |
|---|---|
| RED | Se escribió primero el caso 4 (`muestra el switcher de categorias y grupos y ya no ofrece volver al fixture`) ANTES de implementar la parte 2 del AJUSTE B. `docker compose exec app ./vendor/bin/pest tests/Feature/TablaPosicionesDefaultTest.php` → **1 failed, 3 passed**: el caso nuevo falla en `toContain($urlOtraCategoria)` (el HTML no contiene el href de la otra categoría: no hay switcher). |
| GREEN | Tras controller + vista → `...pest NavegacionPublicaTest.php TablaPosicionesDefaultTest.php` → **12 passed (54 assertions)** = 8 + 4. |
| TRIANGULATE | Caso 4 además exige `toContain($urlGrupoDefault)` (URL con `idGrupo=` que SOLO existe si la fila de grupos renderiza: valida el branch `$grupos->count() > 1`) y `not->toContain('Volver al fixture')`. |

Ajustes sobre tests/contexto preexistentes (documentados):

1. **Contexto ampliado:** `crearContextoPosicionesDefault()` ahora crea un segundo grupo (`Grupo C`) en la categoría primera y devuelve `grupoPrimeraCategoria` + `grupoAdicionalPrimera`. Necesario porque la fila de grupos solo aparece con `count() > 1` y el caso 4 pedido exige "el link del grupo de la categoría default". Los 3 tests previos siguen verdes sin cambios de asertos (el default sigue resolviendo al primer grupo por `orderBy('id')`).
2. **Test 2 ajustado:** `not->toContain('Sub 17')` → `not->toContain('Equipo Norte')`. Con el switcher, el chip de la otra categoría SÍ muestra "Sub 17" en la página de Sub 15 (comportamiento deseado), así que el aserto viejo contradecía la UI nueva; `Equipo Norte` (equipo de la categoría default) mantiene la intención original: la tabla no cayó al default.
3. **Escape de entidades:** Blade sirve los href con `&` como `&amp;` (verificado con `e()` en tinker), así que el caso 4 decodifica con `html_entity_decode($html, ENT_QUOTES)` antes de comparar contra las URLs de `route()`.
4. **Curso de trabajo:** se hizo el AJUSTE A primero, luego test (RED), luego AJUSTE B (GREEN), documentado en lugar del orden estricto B-antes-del-test; la consigna lo admite explícitamente.

### Validaciones ejecutadas (todas en Docker)

1. `docker compose exec app ./vendor/bin/pest tests/Feature/NavegacionPublicaTest.php tests/Feature/TablaPosicionesDefaultTest.php` → **12 passed, 0 failed (54 assertions)** = 8 + 4.
2. `docker compose exec app ./vendor/bin/pest --filter="ProfileTest"` → **4 failed, 1 passed** — baseline preexistente intacto (4×419 CSRF).
3. `docker compose exec app ./vendor/bin/pint --test app/Http/Controllers/ControladorHome.php tests/Feature/TablaPosicionesDefaultTest.php` → "2 files, 1 style issue": SOLO `ControladorHome` por la deuda preexistente (`new_with_parentheses, fully_qual…` sobre imports y `\Carbon\Carbon`, verificado con `pint -v`: el diff propuesto no toca ninguna línea nueva de esta ronda). Test limpio. No se corrigió la deuda (regla del parent).
4. `git diff --stat` vs `git diff -w --stat`: **idénticos en 10 de los 11 archivos de esta ronda** (ControladorHome 41/41; los 8 layouts simples 4/4). Única excepción: `tabla-posiciones` 37 vs 33 — artefacto del algoritmo `diff -w`, que alinea líneas genéricas (`</a>`, `@endif`) del botón eliminado con las del switcher agregado en la misma zona. **No hubo reindentación**: el diff completo muestra que las únicas líneas eliminadas del archivo son exactamente root+wrapper (2) y las 7 del botón "Volver al fixture"; cero líneas de contexto modificadas. Verificación hecha con `git diff | grep '^-'` sobre todos los archivos tocados.
5. Curl de humo (id real vía tinker, `idEdicion=10`):
   - `GET /tabla-de-posiciones?idEdicion=10` → **200**; `grep -c "Categoría no encontrada"` = **0**; `grep -c 'aria-label="Cambiar categoría"'` = **1**; `grep -c "Volver al fixture"` = **0**.
   - Fila de grupos no observable en dev (la edición 10 no tiene categorías con 2+ grupos: verificado por query); queda cubierta por el caso 4 automatizado.
   - `GET /tabla-de-goleadores` → `grep -c 'sticky top-0'` = **1**. `GET /tabla-de-posiciones?idEdicion=10` → **1**.
6. Archivos ajenos intactos: `.gitignore`, `opencode.json`, `sync-db.sh` (sus diffs siguen siendo preexistentes del working tree), `app/Providers/AppServiceProvider.php` y `navigation.blade.php` sin cambios en esta ronda.

### Checklist manual (Ronda 3) — para el usuario, con `npm run dev`

1. **Sticky real en cada página pública:** scrollear home, fixture, tabla-de-posiciones, tabla-de-goleadores, instancias-finales, campeones, subcampeones, goleadores, vallas y tabla-de-vallas: la barra de secciones debe quedar fija arriba del viewport en TODAS (antes solo en home).
2. **Cambiar categorías desde Posiciones:** en `/tabla-de-posiciones`, usar los chips de categoría: la URL cambia a `idCategoria=X` (sin `idGrupo`), el título y la tabla actualizan a esa categoría, el chip activo queda resaltado en amarillo con `aria-current="page"`. Verificar también que al entrar sin parámetros el chip de la primera categoría viene activo.
3. **Fila de grupos:** en una categoría con 2+ grupos debe aparecer la segunda fila de chips; en categorías con un único grupo no debe verse ninguna fila extra. (En la data actual de dev ninguna categoría tiene 2+ grupos: validar en producción o con data de prueba.)
4. **Fondo decorativo sin desborde:** sin el clip en el root, recorrer las páginas en viewport angosto y confirmar que NO hay scroll horizontal de página y que la imagen/gradiente de fondo no se desborda (el recorte ahora vive en el wrapper `absolute inset-0 overflow-hidden`).
5. **Focos y teclado:** con Tab recorrer los chips nuevos: anillo `focus-visible:ring-[#f7f280]/40` visible en activo e inactivos.
6. **Regresión del "Volver al fixture":** confirmar que ya no existe ese botón en Posiciones y que el acceso al fixture sigue disponible desde la barra de secciones.

Estado de la Ronda 3: implementación y validación automatizada completas. Sin commits (restricción del parent).

## Ronda 4 — UNA sola barra sticky (nav unificada) + scrollbar oculto en chips

### Antes / después estructural (`resources/views/layouts/navigation.blade.php`)

| | Antes (Rondas 1–3) | Después (Ronda 4) |
|---|---|---|
| Barras | `<nav aria-label="Navegación principal" class="relative z-50 ...">` (logo+auth, NO sticky) + `<nav aria-label="Secciones del torneo" class="sticky top-0 z-50 ...">` (chips de secciones) | UNA sola `<nav aria-label="Navegación principal" class="sticky top-0 z-50 ...">` que envuelve TODO; la segunda nav DEJA DE EXISTIR (`grep 'Secciones del torneo'` = 0) |
| `$itemsSeccion` | definida dentro de la segunda nav | definida UNA sola vez en `@php` al inicio del nav y reutilizada en ambas representaciones |
| Desktop (≥lg) | chips scrollables en barra separada | links inline `hidden lg:flex items-center gap-6` entre el logo y el bloque de auth, mismos `<a>` (mismas clases y `aria-current`) |
| Mobile (<lg) | chips scrollables en barra separada | fila de chips DENTRO de la barra: `id="nav-secciones-mobile"`, `lg:hidden`, `border-t border-white/10` + `pt-3` de separación interna, `pb-4` conservado, scrollbar oculto con `[scrollbar-width:none]` + `[&::-webkit-scrollbar]:hidden` (bajo lg `max-w-6xl` no limita: el border-t da ancho completo) |
| Auto-scroll JS | `querySelector('nav[aria-label="Secciones del torneo"] a[aria-current="page"]')` incondicional | `var fila = document.getElementById('nav-secciones-mobile'); if (fila && fila.scrollWidth > fila.clientWidth) { ... }` — solo scrollea si la fila realmente scrollea (en desktop `display:none` ⇒ no-op) y usa el activo DENTRO de esa fila |

Logo, bloque de auth, dropdown y clases del contenedor flexible: intactos (verificado en `git diff`: el bloque de auth aparece solo como contexto; el par -/+ del logo y "Panel de administración" es de la Ronda 1 acumulada sin commit, no de esta ronda).

### Chips de Posiciones (AJUSTE B)

Filas de categorías y grupos (`role="group"`): se agregó `[scrollbar-width:none] [&::-webkit-scrollbar]:hidden` (idéntico a la nav). Nada más tocado en la vista. En la edición 10 de dev la fila de grupos no renderiza (no hay categorías con 2+ grupos, ya documentado en Ronda 3), por eso el humo cuenta 2 apariciones de `scrollbar-width:none` (nav mobile + chips de categoría).

### Tests (AJUSTE C) — TDD

| Fase | Evidencia |
|---|---|
| RED | Tests ajustados ANTES de tocar vistas → `docker compose exec app ./vendor/bin/pest tests/Feature/NavegacionPublicaTest.php` → **3 failed, 5 passed (23 assertions)**: los 2 conteos fallan en `1 is identical to 2` (aún hay una sola representación) y el guard nuevo de barra única falla (label `Navegación principal` ×2 y `Secciones del torneo` presente). Sin 500. |
| GREEN | Tras nav + chips → `...pest NavegacionPublicaTest.php TablaPosicionesDefaultTest.php` → **12 passed (57 assertions)**. |
| TRIANGULATE | Guard estructural en el test de cinco links: `aria-label="Navegación principal"` exactamente 1 sobre el HTML completo (a prueba de navs de paginación, que no usan ese label), `not->toContain('Secciones del torneo')` y `toContain('sticky top-0')`. |

Ajustes de tests (todos dentro de la intención "el activo marcado, los otros no"):

1. Los conteos de `aria-current="page"` en la nav pasan de 1 a **2** con comentario: cada link de sección vive DOS veces en el DOM (inline desktop + chips mobile; por viewport solo uno es visible) y el activo se marca exactamente una vez por representación. Los demás asserts no se debilitaron: el activo de la primera representación debe llevar `aria-current="page"` y los otros 4 links no deben llevarlo en ninguna de las dos.
2. Helper `navPublica()`: comentario actualizado (UNA sola barra sticky); el regex `preg_match_all` se mantiene y sigue siendo válido con 1..n navs.
3. Test de los cinco links: no contaba ocurrencias (solo `not->toBeNull` + href del primer match), no requirió cambios por duplicación; se le agregó el guard estructural de barra única.

### Validaciones ejecutadas (todas en Docker)

1. `docker compose exec app ./vendor/bin/pest tests/Feature/NavegacionPublicaTest.php tests/Feature/TablaPosicionesDefaultTest.php` → **12 passed, 0 failed (57 assertions)**.
2. `docker compose exec app ./vendor/bin/pest --filter="ProfileTest"` → **4 failed, 1 passed** — baseline preexistente intacto (4×419 CSRF).
3. `docker compose exec app ./vendor/bin/pint --test tests/Feature/NavegacionPublicaTest.php` → **PASS (1 file)**.
4. `git diff --stat` vs `git diff -w --stat` (solo las 2 vistas trackeadas): navigation **53/53 idénticos** (el cambio de esta ronda es adición pura + `relative`→`sticky`; cero reindentación de bloques que no cambiaron); tabla-posiciones 37/33 — diferencia esperada del algoritmo `diff -w` en la zona del switcher de la Ronda 3 (documentada ahí); mis 2 líneas nuevas son edits de atributo en una sola línea, sin movimiento de bloques.
5. Curl de humo (`idEdicion=10` real):
   - `/tabla-de-posiciones?idEdicion=10` → **200**: `Secciones del torneo` = **0**; `aria-label="Navegación principal"` = **1**; `sticky top-0` = **1**; `nav-secciones-mobile` = 2 (id + selector JS); `scrollbar-width:none` = 2.
   - `/` → **200**: mismos valores; además `<nav` totales en la página = **1** (ni paginación ni barras extra).
6. Archivos ajenos intactos: `.gitignore`, `opencode.json`, `sync-db.sh` y el resto del working tree sin cambios en esta ronda; `ControladorHome`, `AppServiceProvider`, `NavigationComposer` y los 8 layouts no se tocaron.

### Checklist manual (Ronda 4) — para el usuario, con `npm run dev`

1. **Desktop: UNA barra sticky** en cada página pública: al scrollear queda fija arriba una ÚNICA barra (logo + secciones inline + auth), sin barra de secciones apilada debajo y sin doble borde.
2. **Desktop: sin scrollbar visible**: los links de sección son inline (sin overflow), no debe verse ninguna barra de scroll horizontal en la nav.
3. **Mobile: barra sticky de dos filas**: arriba logo+auth, debajo (separada por línea `border-white/10`) los chips scrollables SIN scrollbar visible; al scrollear la barra completa queda fija.
4. **Auto-scroll mobile**: en viewport angosto, entrar a una sección cuyo chip activo quede fuera de vista: debe auto-centrarse suave; en desktop el script no debe hacer nada.
5. **Activo marcado en ambas representaciones**: en cada página el link/chip activo lleva borde y color `#f7f280` + `aria-current="page"` (visible en el DOM dos veces: desktop y mobile).
6. **Chips de Posiciones sin scrollbar**: en `/tabla-de-posiciones`, las filas de categorías/grupos scrollean horizontal sin mostrar la barra nativa del screenshot.
7. **Breakpoint lg**: achicar la ventana cruzando 1024px: los links inline desaparecen y aparecen los chips (y viceversa), sin doble nav en ningún punto intermedio.

Estado de la Ronda 4: implementación y validación automatizada completas. Sin commits (restricción del parent).
