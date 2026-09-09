# Apply Progress — goleadores-filtros-url

## Estado: implementación completa (T1→T6), sin commit

skill_resolution: paths-injected (`laravel-specialist` cargada antes de trabajar).

## T1 — RED (evidencia capturada, sin editar nada)

Comando: `docker compose exec app php artisan test --compact --filter=TablaGoleadoresBusqueda`

```
  FAILED  Tests\Feature\Panel\TablaGoleadoresBusquedaTest > it encuentra un…
  Expected response status code [200] but received 302.
  Failed asserting that 302 is identical to 200.
  at tests/Feature/Panel/TablaGoleadoresBusquedaTest.php:80

  Tests:    2 failed (2 assertions)
  Duration: 9.87s
```

## T2 — GREEN (controlador)

`app/Http/Controllers/ControladorHome.php::tablaGoleadores`:
- Eliminado el bloque PRG completo (`$sessionKey`, `$filtroKeys`, `session()->put`, `redirect()->route('tabla-goleadores')`).
- Estado ahora desde `$request->query()` (URL como única fuente de verdad).
- `->appends($appendParams)` con whitelist explícita (`array_filter`) sobre `idEdicion, idCategoria, search_value, sort, dir`.
- Rama AJAX pasa `idEdicion`, `idCategoria`, `searchValue` al parcial.

Salida GREEN:

```
  Tests:    2 passed (8 assertions)
  Duration: 9.64s
```

## T3 — GREEN (parcial)

`resources/views/partials/goleadores-resultados.blade.php`:
- `$queryBase` + `$urlOrden()` con defaults `?? null` (compatible con `PlanillaPublicaTest`, que no pasa esas variables).
- Los 4 links `.orden-goleadores` (nombre, equipo, categoría, goles) ahora usan `$urlOrden(...)`.

## T4 — GREEN (layout JS)

`resources/views/layouts/tabla-goleadores.blade.php` (solo `<script>`):
- `loadResults(params, opciones = {})` parte de `window.location.search` y aplica los cambios encima.
- `history.pushState` tras fetch exitoso; `history.replaceState` cuando viene de `popstate`.
- Listener `popstate` registrado al final de `DOMContentLoaded`.
- Búsqueda y categoría ahora mandan `page: 1`.
- Cobertura: solo manual (JS vanilla sin harness de browser, sin dependencias nuevas).

## T5 — TRIANGULATE

Test nuevo `tests/Feature/Panel/TablaGoleadoresUrlFiltrosTest.php` (Pest, helpers renombrados `crearContextoUrlFiltros` / `crearGoleadorUrlFiltros` para no colisionar con los globales del otro archivo). 4 tests:

1. GET con `?idEdicion=X` → 200, muestra goleador y nombre de edición.
2. Links de orden preservan `idEdicion=...` y `search_value=...` en la URL.
3. AJAX con query devuelve el parcial sin `<html`.
4. `idCategoria` de URL excluye otra categoría.

```
  PASS  Tests\Feature\Panel\TablaGoleadoresBusquedaTest   (2 tests)
  PASS  Tests\Feature\Panel\TablaGoleadoresUrlFiltrosTest (4 tests)
  Tests:    6 passed (21 assertions)
  Duration: 9.53s
```

## T6 — REFACTOR + verificación manual

### Pint

`docker compose exec app ./vendor/bin/pint --test app/Http/Controllers/ControladorHome.php tests/Feature/Panel/TablaGoleadoresUrlFiltrosTest.php`:

```
  ⨯ app/Http/Controllers/ControladorHome.php new_with_parentheses, fully_qual…
  FAIL .... 2 files, 1 style issue
  PASS (TablaGoleadoresUrlFiltrosTest.php — archivo nuevo limpio)
```

El único archivo con issues es `ControladorHome.php` por deuda **preexistente** (imports fully-qualified, `new_with_parentheses`): falla igual en el árbol limpio (verificado con `git stash`). Las líneas nuevas (`$appendParams`, `$filtros`) no aparecen en el diff de Pint (`grep -c appendParams` = 0). No se arregló por restricción explícita.

### Regresión PlanillaPublicaTest

`1 failed, 12 passed` — el fallo (`ultima_actualizacion` timezone `19:45` vs `20:15`) es **preexistente**: se reproduce idéntico con los cambios stasheados. Ajenos a este change.

### curl manual (edición 10)

```
$ curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' ".../tabla-de-goleadores?idEdicion=10"
200                       (sin redirect; antes: 302)

$ curl -s "...?idEdicion=10" | grep -c 'transition hover:bg-white/5'
30                        (30 filas del paginador)

$ curl -s "...?idEdicion=10" | grep -o 'pushState\|popstate' | sort -u
popstate
pushState                 (ambos presentes en el JS servido)

$ curl -s -H 'X-Requested-With: XMLHttpRequest' "...?idEdicion=10" | grep -c '<html'
0                         (AJAX devuelve solo el parcial)
```

## Archivos modificados

- `app/Http/Controllers/ControladorHome.php` (solo método `tablaGoleadores`)
- `resources/views/partials/goleadores-resultados.blade.php`
- `resources/views/layouts/tabla-goleadores.blade.php` (solo `<script>`)
- `tests/Feature/Panel/TablaGoleadoresUrlFiltrosTest.php` (nuevo)
- `openspec/changes/goleadores-filtros-url/tasks.md` (checkboxes)
- `openspec/changes/goleadores-filtros-url/apply-progress.md` (este archivo)

## Desvíos respecto de instructions.md (justificados)

1. **T3:** instructions pedía `$queryBase` desde variables inyectadas (`$idEdicion ?? null`), mientras `tasks.md` pedía `request()->except(['page'])`. Se siguió instructions (whitelist explícita, más seguro: un query param arbitrario no entra en los links de orden). `request()->except()` queda solo en los links del paginador vía `appends()` whitelist.
2. **T5:** `tasks.md` pedía agregar el test dentro de `TablaGoleadoresBusquedaTest.php`; instructions (superficie de edición autoritativa) pedían archivo nuevo con helpers renombrados. Se siguió instructions.
3. Sin commits, sin branches, sin dependencias nuevas, deuda de Pint intacta.

## Tareas restantes

Ninguna (`- [ ]` sin marcar: 0).
