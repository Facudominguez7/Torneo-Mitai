# Tasks — goleadores-filtros-url

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 150-220 (alineado con proposal §11) |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | single-pr |
| Delivery strategy | ask-on-risk |

```text
Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low
```

Forecast por tarea: T1 = 0 líneas (solo evidencia), T2 = 40-60 (controlador), T3 = 15-25 (parcial), T4 = 40-70 (layout JS), T5 = 0-40 (tests). Total ≤ ~220 líneas, holgado bajo el umbral de 400.

skill_resolution: paths-injected (`laravel-specialist` cargada).

---

## Secuencia Strict TDD

El RED ya existe en el repo (2 tests de `TablaGoleadoresBusquedaTest` en rojo en main, aserciones intocables). La secuencia es: **T1 captura RED → T2 GREEN (controlador) → T3/T4 GREEN (vistas) → T5 TRIANGULATE → T6 REFACTOR acotado + verificación manual**.

### T1 — RED: registrar evidencia existente (no se escribe test nuevo)

- [x] Correr en Docker el gate primario y capturar la salida real del RED: `docker compose exec app php artisan test --compact --filter=TablaGoleadoresBusqueda`. Evidencia esperada: 2 tests fallando con `Expected response status code [200] but received 302` (línea 80 de `tests/Feature/Panel/TablaGoleadoresBusquedaTest.php`). Pegar la salida cruda en la PR / nota del change como baseline. No modificar el archivo de test. Complementar con el baseline curl: `curl -s -o /dev/null -w '%{http_code}\n' "http://localhost:PUERTO/tabla-de-goleadores?idEdicion=10"` → debe devolver `302` hoy. **Criterio de verificación:** salida del comando capturada mostrando exactamente 2 fallos `302` y curl mostrando `302`. <!-- sdd-owner: implementation -->

### T2 — GREEN (backend): port del patrón `tablaVallas` a `tablaGoleadores`

- [x] En `app/Http/Controllers/ControladorHome.php`, método `tablaGoleadores`: eliminar la clave de sesión `tabla_goleadores_filtros` y el `redirect()->route('tabla-goleadores')`; resolver todo el estado desde `$request->query()` con `array_filter([...])` sobre `idEdicion, idCategoria, search_value, sort, dir`, y agregar `->appends(...)` al paginador de `$goleadores_t`. Reutilizar explícitamente el patrón de `ControladorHome::tablaVallas()` (mismo archivo, referencia URL-first del repo). No agregar variables nuevas al `compact(...)`; solo reordenar de dónde salen `idEdicion`, `idCategoria`, `searchValue`, `sortKey`, `dir`. No tocar la deuda de Pint preexistente (imports fully-qualified) fuera del método. **Criterio de verificación:** `docker compose exec app php artisan test --compact --filter=TablaGoleadoresBusqueda` pasa con los 2 tests en verde, sin ninguna edición en las aserciones del test. <!-- sdd-owner: implementation -->

### T3 — GREEN (parcial): links de orden con estado completo

- [x] En `resources/views/partials/goleadores-resultados.blade.php`: portar el patrón de `resources/views/layouts/tabla-vallas.blade.php:132-146` — `$queryBase = array_filter(request()->except(['page']))` y `array_merge($queryBase, ['sort' => ..., 'dir' => ...])` en los 4 links de orden (nombre, equipo, categoría, goles). Sin variables nuevas requeridas (usar `request()` dentro de Blade; si hace falta algo, default `$var ?? null`). **Criterio de verificación:** `docker compose exec app ./vendor/bin/pest tests/Feature/Panel/TablaGoleadoresBusquedaTest.php` sigue en verde y la suite de vistas públicas (`tests/Feature/PlanillaPublicaTest.php`) no se rompe: `docker compose exec app php artisan test --compact tests/Feature/PlanillaPublicaTest.php`. <!-- sdd-owner: implementation -->

### T4 — GREEN (layout JS): payload completo + History API ⚠️ RIESGOSA

- [x] En `resources/views/layouts/tabla-goleadores.blade.php`: (a) `loadResults(params)` deja de mandar el parámetro suelto — arma el payload completo desde un objeto de estado sincronizado con la query actual de la URL, igual que `tabla-vallas` reconstruye la URL completa en `updateUrl()`; (b) al resolver cada `loadResults()` (post-debounce, no por tecla) hacer `history.pushState` con la URL de estado completo; (c) listener `popstate` que lee los query params de la URL restaurada y vuelve a llamar `loadResults()`. APIs nativas, cero dependencias nuevas. **Riesgo:** sin cobertura de test automatizado en este stack (JS vanilla sin pipeline de test de browser). Chequeo que la cubre (manual, reproducible): 1) `curl -s "http://localhost:PUERTO/tabla-de-goleadores?idEdicion=10" | grep -c 'pushState\|popstate'` ≥ 2 (el código está servido); 2) en browser: cargar la tabla con edición 10, cambiar categoría → la URL visible cambia sin recarga (DevTools Network: solo 1 request XHR del parcial, sin document navigation); escribir en búsqueda → una sola entrada de historial post-debounce; Atrás restaura los resultados previos; repetir en ventana incógnito para confirmar independencia de sesión. **Criterio de verificación:** Pest en verde + ambos chequeos manuales documentados con resultado observado en la PR. <!-- sdd-owner: implementation -->

### T5 — TRIANGULATE: un caso adicional de valor real, sin inflar la suite

- [x] En `tests/Feature/Panel/TablaGoleadoresBusquedaTest.php` (sin tocar los 2 tests existentes), agregar UN test: **independencia de sesión** — cliente B con sesión previa (simular tocando `session(['tabla_goleadores_filtros' => [...otros filtros...]])` en el test case y reusando la sesión con `$this->withSession(...)`) hace el mismo GET con query string que el cliente A, y ambas respuestas contienen el mismo goleador esperado. Esto cubre el scenario "Mismo query, con y sin sesión previa" del spec, que ningún test existente ejercita. No agregar tests de orden ni paginación (cubierto por el patrón espejo de `tabla-vallas`). **Criterio de verificación:** `docker compose exec app ./vendor/bin/pest tests/Feature/Panel/TablaGoleadoresBusquedaTest.php` → 3 tests en verde. **Criterio de triangulación:** el test nuevo debe fallar si se reintroduce lectura de sesión en `tablaGoleadores` (verificar mentalmente/mutación rápida opcional; no commitear la mutación). <!-- sdd-owner: implementation -->

### T6 — REFACTOR acotado + verificación manual end-to-end

- [x] Refactorizar solo dentro del método `tablaGoleadores` y las 2 vistas tocadas (extraer array local de filtros si quedó duplicado; nombrar según dominio en español). No tocar Pint preexistente, ni #102/#103/#104, ni renombrar params. Luego verificación manual completa con edición 10 (242 registros joinables; NO usar ediciones 2/3/6, dan "No existen registros" legítimamente): compartir URL con `idEdicion=10&idCategoria=X&search_value=Y` y abrirla en incógnito (mismo contenido), recargar (estado persiste), links de orden y paginación llevan el estado completo (inspeccionar hrefs en el DOM), Atrás/Adelante coherentes tras filtrar por AJAX. **Criterio de verificación:** `docker compose exec app ./vendor/bin/pint --test app/Http/Controllers/ControladorHome.php resources/views/` acepta el formato del código nuevo (deuda ajena reportada aparte, no bloqueante); suite completa `docker compose exec app php artisan test --compact` sin regresiones; checklist manual completo asentado en la PR. <!-- sdd-owner: implementation -->

---

## Mapeo tarea ↔ requirement

| Requirement (spec) | Tarea(s) | Cobertura |
|---|---|---|
| Resolución de estado desde la URL | T2 (+ T1 como RED de 2 de sus scenarios) | Automatizada (2 tests existentes + curl) |
| Ausencia de redirect en peticiones no-AJAX | T1 (RED) → T2 (GREEN) | Automatizada (assertOk) + curl sin cookies |
| Independencia de sesión | T5 | Automatizada (test nuevo TRIANGULATE) |
| Estado completo en links de orden | T3 | Automatizada (render + inspección de hrefs en test/mantra manual) + manual T6 |
| Estado completo en links de paginación | T2 (`appends`) | Automatizada parcialmente + manual T6 (inspección hrefs del paginador) |
| Actualizaciones AJAX preservan flujo sin recarga | T4 | Solo manual (JS sin pipeline de test de browser en este stack) |
| Sincronización de History API (pushState/popstate) | T4 | Solo manual: curl `grep pushState\|popstate` + pasos browser (incógnito, Network tab, botón Atrás). Motivo: `history.pushState`/`popstate` solo existen en navegador real; el stack no tiene pruebas de browser ni JS testing harness, y agregar uno viola la restricción de no-sumar dependencias |
| No regresión de otras vistas públicas | T3, T6 | Automatizada (suite completa `php artisan test --compact` en verde sin tocar tests ajenos) |

## Verificación manual explícita

- **Qué queda solo manual:** todo el behavior de History API y el flujo AJAX parcial (T4), más la inspección de hrefs de orden/paginación con estado completo en browser real (T6).
- **Por qué:** JS vanilla en Blade sin runner de browser; el spec lo permite como verificación manual y la restricción prohíbe dependencias nuevas (Cypress/Playwright estarían fuera de alcance).
- **Trampa de datos:** usar siempre edición **10** para la verificación manual (242 registros joinables). Ediciones 2, 3 y 6 muestran "No existen registros" legítimamente y no sirven para validar filtros.
