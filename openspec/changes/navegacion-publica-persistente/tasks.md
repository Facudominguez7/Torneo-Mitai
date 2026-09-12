# Tasks — Navegación pública persistente (issue #97)

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~150 (rango 130–190) |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | single PR |
| Delivery strategy | single-pr (ask-on-risk no aplica: riesgo Low) |
| Chain strategy | pending |

```text
Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low
```

Forecast por tarea: T1 ~+90 (test nuevo), T2 ~+10 (composer), T3 ~+50/~5 modificadas (partial), T4 ~0 (pint). Total ≈150 < 400.

## Secuencia TDD (Strict)

1. **RED** (T1): escribir `tests/Feature/NavegacionPublicaTest.php` completo; corre y falla porque la nav no tiene los 5 links ni `aria-current`.
   ```bash
   docker compose exec app ./vendor/bin/pest tests/Feature/NavegacionPublicaTest.php
   ```
   Esperado: fallos en asserts de links/aria-current (y nada de errores 500).
2. **GREEN** (T2 + T3): registrar el composer y agregar la fila de links al partial; los tests pasan.
3. **TRIANGULATE/REFACTOR** (T4): `pint --test` + smoke de regresión (`ProfileTest`), sin cambios funcionales.

## Tareas

### T1 — RED: tests de navegación pública (`tests/Feature/NavegacionPublicaTest.php`, nuevo)
Crear test Pest con factories existentes cubriendo: (a) guest en `fixture` con `idEdicion` ve los 5 links con `idEdicion` en cada `href`; (b) el link de Posiciones no incluye `idCategoria`; (c) `aria-current="page"` solo en "Partidos" en `fixture`; (d) fallback del composer: en `tabla-posiciones` sin `idEdicion`/`EdicionSeleccionada`, los links igual resuelven `idEdicion`; (e) edición explícita del controlador no es sobrescrita; (f) guest ve login/registro sin perfil/admin; (g) autenticado no-admin ve perfil sin "Panel de administración"; (h) admin ve los tres grupos. Para `tabla-goleadores` usar `assertRedirect` + follow (PRG preexistente); asserts de href con `idEdicion` deben contemplar el mundo URL-first de PR #113 (matchear presencia del parámetro, no el orden de query string).
- Verificación: `docker compose exec app ./vendor/bin/pest tests/Feature/NavegacionPublicaTest.php` → falla (links inexistentes), sin errores 500.
- Mapeo: R1, R2, R3, R4.
- Forecast: +90.
- [x] Implementar y verificar el comportamiento. <!-- sdd-owner: implementation -->

### T2 — GREEN: view composer scoped al partial (`app/Providers/AppServiceProvider.php`)
Registrar en `boot()` un `View::composer('layouts.navigation', ...)` que setea `ultimaEdicion = Edicion::latest('id')->first()` **solo si** `!array_key_exists('ultimaEdicion', $view->getData())`. No tocar el composer existente de `Panel.admin` (no setear jamás `EdicionSeleccionada`): el fallback no pisa la selección explícita del controlador ni el panel.
- Verificación: `docker compose exec app php artisan test --compact --filter=NavegacionPublicaTest` → los casos de fallback/edición-explícita pasan (los de links siguen rojos hasta T3). Re-verificar `docker compose exec app php artisan test --compact --filter="Panel"` si existe suite de panel: 200 sin regresión.
- Mapeo: R2.
- Riesgo: colisión con `EdicionSeleccionada` — mitigado por guard de `array_key_exists` y nombre de variable distinto.
- Forecast: +10.
- [x] Implementar y verificar el comportamiento. <!-- sdd-owner: implementation -->

### T3 — GREEN: fila de links de sección (`resources/views/layouts/navigation.blade.php`)
Agregar segunda fila dentro del `<nav>`: array `['label' => ..., 'ruta' => ..., 'patterns' => [...]]` con los 5 items (`home`, `fixture`, `tabla-posiciones`, `tabla-goleadores`, `instancias-finales`), construyendo cada href con `route($ruta, ['idEdicion' => optional($edicionActiva)->id])` (Laravel omite `idEdicion` si es null) y **sin** `idCategoria` en Posiciones. Patrón exacto de patterns: Inicio `['home']`, Partidos `['fixture']`, Posiciones `['tabla-posiciones']`, Goleadores `['tabla-goleadores']`, Finales `['instancias-finales']` — array plano de strings exactas, SIN wildcards (`home.*`) para que Inicio no quede activo en otras rutas. Resaltado con `request()->routeIs($item['patterns'])` + `aria-current="page"` (idiom `Panel/admin.blade.php:512-543`). Clases: fila con `flex flex-wrap justify-center gap-x-6 gap-y-2 sm:flex-nowrap overflow-x-auto [&::-webkit-scrollbar]:hidden`; link activo `text-white` + borde/underline (no solo color); foco con token existente `focus-visible:ring-2 focus-visible:ring-[#f7f280]/40 focus:outline-none` en los 5 links nuevos y extenderlo al logo y "Panel de administración". `aria-label="Navegación principal"` en el `<nav>`. Sin `sticky`, sin Alpine nuevo, Tailwind v3.
- Verificación: `docker compose exec app ./vendor/bin/pest tests/Feature/NavegacionPublicaTest.php` → 8/8 passing. Luego smoke: `docker compose exec app php artisan test --compact --filter=ProfileTest` → passing (dashboard/perfil no rotos).
- Mapeo: R1, R3, R4, R6.
- Riesgos: (1) partial compartido por 11 layouts → cubierto por smoke T3b; (2) routeIs con wildcard marcaría Inicio activo en todo → patterns exactos, verificado por el test (c).
- Forecast: +50 / ~5 modificadas.
- [x] Implementar y verificar el comportamiento. <!-- sdd-owner: implementation -->

### T4 — Formato y regresión final
Correr Pint y la suite mínima completa de regresión; no introducir cambios funcionales.
- Verificación:
  ```bash
  docker compose exec app ./vendor/bin/pint --test --dirty
  docker compose exec app php artisan test --compact --filter="NavegacionPublicaTest|ProfileTest"
  ```
  Esperado: Pint OK, todos los tests passing.
- Mapeo: R6, R7 (parcial).
- Forecast: ~0.
- [x] Implementar y verificar el comportamiento. <!-- sdd-owner: implementation -->

### T5 — Verificación manual (visual / mobile)
Con `npm run dev`: en viewport móvil verificar que los 5 links son visibles/accesibles y que no hay scroll horizontal de página (solo del contenedor de la nav); en desktop verificar foco visible con Tab en los 5 links + logo + "Panel de administración", y `aria-current`/estilo activo en `fixture`, `tabla-posiciones`, `dashboard` (guest y admin). Panel admin intacto (no incluye este partial).
- Verificación: checklist manual en `fixture`, `tabla-posiciones`, `dashboard` — sin defectos visuales ni scroll horizontal.
- Mapeo: R5, R6, R7.
- Forecast: 0.
- [ ] Implementar y verificar el comportamiento. <!-- sdd-owner: implementation -->

## Mapeo tarea ↔ requirement

| Requirement | T1 | T2 | T3 | T4 | T5 |
|---|---|---|---|---|---|
| R1 Links de sección con edición conservada | ✅ | | ✅ | | |
| R2 Contexto de edición en toda vista pública | ✅ | ✅ | | | |
| R3 Resaltado de sección activa | ✅ | | ✅ | | ✅ |
| R4 Navegación coherente con rol | ✅ | | ✅ | | |
| R5 Usable en pantallas angostas | | | ✅ (clases) | | ✅ |
| R6 Sin regresión en layouts existentes | | ✅ (guard) | ✅ (smoke) | ✅ | ✅ |
| R7 Panel admin sin cambios | | ✅ (no tocar) | | ✅ | ✅ |

## Verificación manual (resumen)

Queda fuera de tests automatizados: scroll horizontal real en mobile (R5), foco visible con teclado, contraste del estado activo, y comportamiento visual del panel admin (R7). Todo lo observable vía HTTP (links, hrefs, aria-current, roles, fallback del composer) está cubierto por `NavegacionPublicaTest` + `ProfileTest`.
