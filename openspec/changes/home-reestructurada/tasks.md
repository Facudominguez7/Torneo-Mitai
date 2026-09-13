# Tasks — `home-reestructurada` (issue #100)

Branches: PR1 sobre `feat/seguir-equipo-vista` (trae #99) → merge a `main`;
PR2 (`feat/home-reestructurada-vista`) apilada sobre PR1.

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~380-450 brutas / ~50-150 NETAS (extracción mueve ~220 líneas de fixture a un componente reutilizado 3×; el change elimina ~290 palabras de prosa + dead code del controlador) |
| 400-line budget risk | Medium (brutas) / Low (netas) |
| Chained PRs recommended | Yes |
| Suggested split | PR1 (componente + fixture mecánico) → PR2 (home + controlador) |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

```text
Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: Medium
```

Justificación del split: PR1 es mecánica, de bajo riesgo visual y verificable
pixel-a-pixel (Playwright antes/después). PR2 es la de mayor superficie de
revisión (jerarquía completa de la home). Si al implementar PR1+PR2 el conteo
bruto queda bajo 400 y el dueño lo prefiere, pueden unificarse (decisión
`ask-on-risk`).

Convenciones de verificación: Pest dentro de Docker
(`docker compose exec app ./vendor/bin/pest --filter="..."`), formato con
`docker compose exec app ./vendor/bin/pint --dirty` (no `--test` global:
`ControladorHome.php` ya falla en `main` por deuda preexistente). Playwright
con toolbox `~/.local/mitai-playwright`. Baseline de fallos preexistentes: ~30F
(no introducir nuevos; no "arreglar" los existentes).

Comandos base Playwright (toolbox §8, Chrome del sistema):

```bash
node ~/.local/mitai-playwright/shot.js <url> <salida.png> 1280 900
node ~/.local/mitai-playwright/shot.js <url> <salida.png> 390 844
```

---

## PR1 — Extracción mecánica de `<x-tarjeta-partido>` + fixture sin regresión

Rama: apilada sobre `feat/seguir-equipo-vista`, merge directo a `main`.
Alcanza Requirement 4 (tarjeta compartida, mitad fixture).

### T1. Captura baseline de fixture (ANTES de tocar nada)

- En la branch base (`feat/seguir-equipo-vista`), levantar Docker y capturar
  fixture en ambos viewports, con al menos un partido estándar y uno
  interzonal visibles:
  - `node ~/.local/mitai-playwright/shot.js "http://localhost/fixture" docs/baseline-fixture-desktop-antes.png 1280 900`
  - `node ~/.local/mitai-playwright/shot.js "http://localhost/fixture" docs/baseline-fixture-mobile-antes.png 390 844`
- Si no existiera captura con interzonal en pantalla, capturar además con
  `?idCategoria=` de una categoría interzonal real (verificar con tinker).
- Guardar también el HTML renderizado de una tarjeta
  (`docker compose exec app php artisan tinker --execute='...'` o curl +
  grep del bloque `id="partido-"`) como referencia byte-relevante.
- Criterio de verificación: dos PNG + fragmento HTML guardados; revisarlos con
  `read`.
- [x] <!-- sdd-owner: implementation -->

### T2. RED — test de no-regresión de fixture sobre el componente

- Crear `tests/Feature/FixtureRenderizaTarjetasTest.php` (Pest, modelos con
  `::create()`, sin factories): dado un partido estándar y uno interzonal en
  la edición, `GET /fixture` renderiza para cada uno `id="partido-{id}"`,
  `data-jugado`, `data-horario`, `data-local`, `data-visitante`.
- Ejecutar en Docker y confirmar que pasa ANTES del refactor (es test de
  caracterización del HTML actual — si algo de esto no existe hoy, ajustar el
  test a la realidad, no al deseo; el objetivo es detectar el delta del
  refactor).
- Criterio: `docker compose exec app ./vendor/bin/pest --filter="FixtureRenderizaTarjetas"` en verde.
- [x] <!-- sdd-owner: implementation -->

### T3. GREEN — crear componente anónimo y reemplazo mecánico

- Crear `resources/views/components/tarjeta-partido.blade.php`:
  `@props(['partido', 'interzonal' => false])`, extracción literal de
  `layouts/fixture.blade.php:236-345` (variante estándar) unificando la
  variante interzonal (`:366-449`, gradiente ámbar + badge) con la clase
  condicionada por `$interzonal`. Mismos data-attributes e `id`.
- En `layouts/fixture.blade.php` reemplazar las 2 copias inline por
  `<x-tarjeta-partido :partido="$p" />` y
  `<x-tarjeta-partido :partido="$p" :interzonal="true" />`. Cero cambios de
  markup, cero "mejoritas".
- Si aparece una variante no prevista (ej. botón de planilla con estado
  distinto): duplicar el markup puntual y documentar deuda en el PR (fallback
  autorizado en proposal).
- Criterio: T2 sigue en verde (`--filter="FixtureRenderizaTarjetas"`); diff de
  `fixture.blade.php` muestra solo reemplazos por el componente.
- [x] <!-- sdd-owner: implementation -->

### T4. TRIANGULATE — igualdad byte-relevante del HTML y capturas después

- Comparar el HTML generado de la misma tarjeta antes/después (fragmento de
  T1 vs. nuevo): idéntico salvo whitespace de compilación de Blade.
- Capturas después:
  - `node ~/.local/mitai-playwright/shot.js "http://localhost/fixture" docs/baseline-fixture-desktop-despues.png 1280 900`
  - `node ~/.local/mitai-playwright/shot.js "http://localhost/fixture" docs/baseline-fixture-mobile-despues.png 390 844`
- Comparar pares antes/después (desktop y mobile) con `read`; diferencias
  visuales = rollback del reemplazo y análisis.
- Suite de regresión de fixture existente
  (`docker compose exec app php artisan test --compact --filter=Fixture`) en
  el mismo estado verde que la baseline (~30F preexistentes, sin nuevos).
- Formato: `docker compose exec app ./vendor/bin/pint --dirty` (solo archivos
  PHP tocados — en PR1 no debería haber ninguno).
- Criterio: capturas idénticas, suite sin nuevos fallos, T2 verde → commit
  PR1: `refactor(fixture): extraer componente <x-tarjeta-partido> sin cambio de render`.
- [x] <!-- sdd-owner: implementation -->

---

## PR2 — Home reestructurada + ControladorHome (apilada sobre PR1)

Rama: `feat/home-reestructurada-vista` sobre PR1. Cubre Requirements 1, 2, 3,
5, 6, 7, 8.

### T5. RED — contrato del controlador (fecha relevante, sin dead code)

- Crear `tests/Feature/HomeFechaRelevanteTest.php` (Pest, `::create()`):
  1. `it('muestra "Próximos partidos" cuando la fecha relevante tiene partidos futuros')`
  2. `it('muestra "Fecha pendiente" cuando la fecha relevante es histórica o sin horario')` — **camino real hoy** (edición 10: 0 futuros), no edge case
  3. `it('lista todos los partidos de la fecha relevante, no un subconjunto')` (fecha con >6 partidos)
  4. `it('no rompe cuando la edición no tiene partidos')`
  5. `it('no consulta User::pluck ni pasa $roles ni $idCategoria')` (inspección de variables compartidas / `DB::listen`)
- Ejecutar y confirmar ROJO con el fallo esperado (etiqueta ausente / variable presente).
- Criterio: `docker compose exec app ./vendor/bin/pest --filter="HomeFechaRelevante"` rojo por las razones correctas.
- [x] <!-- sdd-owner: implementation -->

### T6. GREEN — `ControladorHome::index()`

- Inyectar `FixtureExploradorService` por constructor; reutilizar
  **exactamente** `resolverFechaRelevante()` (sin duplicar el predicado
  `jugado=0 OR NULL` + `horario >= now()`).
- Agregar `$idFechaRelevante`, `$partidosFechaRelevante` (query con join de
  escudos/nombres igual a `fixture()`, **sin límite**, sin filtro por
  categoría ni favorito), `$fechaRelevante`, `$hayPartidosFuturos`.
- Eliminar `$roles = User::pluck('rol')`, `$idCategoria`, y el import de
  `User` si queda huérfano. No tocar el resto del controlador.
- Criterio: T5 en verde; `pint --dirty` sin nueva deuda;
  `--filter="HomeFechaRelevante"` verde.
- [x] <!-- sdd-owner: implementation -->

### T7. RED — jerarquía y prosa de la vista (Requirements 1, 3, 6)

- Crear `tests/Feature/HomeJerarquiaTest.php`:
  - `assertSeeInOrder()` de marcadores de bloque: hero → `id="fecha-relevante"` → `#seguir-equipo-bloque` → accesos secundarios → carrusel → historial (Req 1).
  - `assertDontSee()` de cada texto eliminado (kicker "fútbol formativo", párrafo de 33 palabras, 3 badges, "Organizá tu experiencia", kickers/descripciones de historial, kicker/párrafo de comunidad e institucional) (Req 3/6).
  - Exactamente **un** link a `route('fixture', [...])` fuera de la nav (Req 3).
  - `assertSee` de los 4 chips: `tabla-posiciones`, `tabla-goleadores`, `instancias-finales`, `tabla-vallas` (Req 1, Vallas 4to chip).
  - Home idéntica en bloques para guest y admin (`actingAs`) (Req 8).
- Confirmar ROJO.
- Criterio: `--filter="HomeJerarquia"` rojo por las razones correctas.
- [x] <!-- sdd-owner: implementation -->

### T8. GREEN — reescritura de `welcome.blade.php`

- Hero mínimo (Req 3): `<h1>` = nombre de la edición; conservar
  `<form>`+`<select>` de edición (solo eso de la card lateral); CTA primario
  "Ver partidos" (ancla `#fecha-relevante`); CTA secundario "Seguir mi equipo"
  (ancla `#seguir-equipo-bloque`).
- Bloque vivo (Req 2): `<section id="fecha-relevante">`, etiqueta dinámica
  `Próximos partidos`/`Fecha pendiente`, nombre de la fecha, `foreach` con
  `<x-tarjeta-partido :partido="$p" />` (listado completo), link
  "Ver todos los partidos" → `route('fixture', ['idEdicion' => ...])`.
- **Reubicar sin reescribir** el bloque `#seguir-equipo-bloque` de #99 (Req 5):
  mover el HTML intacto a la posición 4; mismos ids, cero cambios a
  `welcome.js` ni al contrato de hidratación. En la vista diff debe verse
  como traslado, no como reescritura.
- Accesos secundarios: grupo de 4 chips (bloque 5). Carrusel de categorías
  conservado sin kicker/párrafo, sin "Ver todo el fixture". Historial a links
  (icono + título + "Ver más"; `goleadores`/`vallas` históricos solo ahí).
  Comunidad: 3 links, sin prosa. Institucional: recorte de kicker/`<h3>`/`<p>`,
  4 bullets como candidato — **no eliminar sin confirmación explícita del
  dueño** (decisión `ask-on-risk`).
- Criterio: T5 y T7 en verde; suite de navegación
  (`--filter=NavegacionPublica`) sin cambios en su resultado baseline.
- [x] <!-- sdd-owner: implementation -->

### T9. TRIANGULATE — contraste con datos reales y regresión completa

- Correr la suite completa en Docker (`docker compose exec app php artisan
  test --compact`): mismos ~30F preexistentes, cero fallos nuevos.
- Verificar contra la edición real (tinker): la home muestra "Fecha pendiente"
  con los 32 pendientes históricos accesibles en el bloque (camino real).
- `pint --dirty`.
- Criterio: sin fallos nuevos, T5/T7 verdes.
- [x] <!-- sdd-owner: implementation -->

### T10. Verificación visual Playwright de la home (Req 1, 5, 8)

- Capturas de la home nueva:
  - `node ~/.local/mitai-playwright/shot.js "http://localhost/" docs/home-nueva-desktop.png 1280 900`
  - `node ~/.local/mitai-playwright/shot.js "http://localhost/" docs/home-nueva-mobile.png 390 844`
  - Con scroll para verificar el comportamiento del nav sticky
    (`#nav-centinela` / `.nav-compacta` de #97) con la nueva altura del hero.
- Aserciones DOM con script Node + `page.evaluate`: `#fecha-relevante` visible
  y antes de `#seguir-equipo-bloque` en el DOM; etiqueta "Fecha pendiente" presente
  con datos reales; `#seguir-equipo-bloque` hidratado por `welcome.js` con
  `localStorage` pre-poblado (Req 5).
- Revisar las capturas con `read` antes de reportar. Jerarquía primera pantalla
  mobile = info viva, no prosa.
- Criterio: capturas revisadas, aserciones DOM verdes, nav sticky OK.
- [x] <!-- sdd-owner: implementation -->

### T11. Cierre PR2

- Conteo real de líneas añadidas/eliminadas del change completo
  (`git diff main...HEAD --stat`): confirmar expectativa de NETAS negativas o
  bajas; si supera 400 brutas de forma injustificable, reportar antes de abrir PR.
- Commits sugeridos PR2: `feat(home): bloque vivo de fecha relevante en ControladorHome`
  (T5+T6) → `feat(home): reestructurar welcome con jerarquía de 9 bloques` (T7+T8)
  → `test(home): triangulación y verificación visual` (T9+T10).
- Criterio: PR2 abierta con descripción que mapee cada requirement del spec a
  su task/commit.
- [x] <!-- sdd-owner: implementation -->

---

## Mapeo tarea ↔ requirement (8)

| Requirement (spec) | Tareas |
| --- | --- |
| 1. Jerarquía de bloques de la home | T7, T8, T10 |
| 2. Bloque de información viva (fecha relevante) | T5, T6, T8, T9 |
| 3. Hero mínimo sin prosa ni CTAs duplicados | T7, T8, T10 |
| 4. Tarjeta compartida `<x-tarjeta-partido>` | T1, T2, T3, T4 (fixture) · T8 (uso en home) |
| 5. Favorito reubicado, no reescrito (`#seguir-equipo-bloque` + `welcome.js`) | T8, T10 |
| 6. Sin prosa institucional | T7, T8 |
| 7. Sin dead code (`$roles`, `$idCategoria`, `User::pluck`) | T5, T6 |
| 8. Compatibilidad nav pública / guest vs admin | T8 (regresión NavegacionPublica), T9, T10 |

## Riesgos y mitigaciones

- **Extracción mecánica (Req 4):** mitigada por T1 (baseline HTML+capturas
  antes) y T4 (comparación byte-relevante + pares de capturas). Cualquier delta
  visual → rollback del reemplazo.
- **Bloque vivo sin partidos futuros:** el camino "Fecha pendiente" es el real
  hoy (edición 10: 0 futuros); probado explícitamente en T5.2 y verificado
  contra datos reales en T9/T10. El bloque nunca queda vacío.
- **Contrato `welcome.js` de #99:** el bloque se traslada, no se reescribe
  (T8); verificación de hidratación con localStorage pre-poblado en T10. Si la
  branch base cambió ids/selectors, reconciliar en T8 antes de continuar.
- **Pint preexistente:** `ControladorHome.php` ya falla `pint --test` en
  `main`; usar `--dirty` y no reportar como regresión ni ampliar deuda.
- **Baseline ~30F:** la suite completa puede tener fallos preexistentes;
  el criterio es "cero fallos nuevos", no "suite 100% verde".
