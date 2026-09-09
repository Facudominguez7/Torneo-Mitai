# Propuesta — Filtros públicos de la tabla de goleadores en la URL

Change: `goleadores-filtros-url`
Cierra: #96 (P0), #101 (P1)
Independiente de: `ux-portal-publico` (change paraguas, solo contiene el explore de las 9 issues)

## 1. Problema

`ControladorHome::tablaGoleadores()` es el único método público del controlador que
no es URL-first: usa un patrón PRG (Post/Redirect/Get) sobre la clave de sesión
`tabla_goleadores_filtros`. Toda petición con query string se persiste en sesión y,
si no es AJAX, redirige a `route('tabla-goleadores')` **sin parámetros**. El estado
(`idEdicion`, `idCategoria`, `sort`, `dir`, `search_value`, `page`) se lee después
solo desde sesión.

**Evidencia objetiva (ya verificada, no repetir):**
- Baseline medido en `main`: `GET /tabla-de-goleadores?idEdicion=10` sin cookie de
  sesión previa devuelve `302` hacia la URL sin parámetros → la vista real se
  renderiza sin `idEdicion`, 0 filas.
- 2 tests rojos ya commiteados en `main`
  (`tests/Feature/Panel/TablaGoleadoresBusquedaTest.php`): ambos esperan `200` y
  reciben `302` en la línea 80, porque el primer `GET` con query string siempre
  redirige para un cliente no-AJAX.
- Consecuencia downstream en las vistas: los links de orden en
  `goleadores-resultados.blade.php` solo llevan `sort`/`dir` (pierden `idEdicion`,
  `idCategoria`, `search_value`), y el paginador no tiene `appends()`, así que sus
  links solo llevan `?page=N`.

Esto rompe compartir enlaces, recargar la página, abrir en otro navegador o pestaña
de incógnito, y el botón Atrás/Adelante — exactamente lo que piden #96 y #101.

## 2. Goals

- `GET /tabla-de-goleadores?idEdicion=X&idCategoria=Y&sort=...&dir=...&search_value=...&page=N`
  renderiza el mismo contenido sin depender de sesión ni de una petición previa.
- Copiar esa URL y abrirla en otro navegador/incógnito reproduce el mismo estado.
- Recargar la página conserva el estado (está en la URL, no en sesión).
- Los links de orden y de paginación llevan siempre el estado completo
  (`idEdicion`, `idCategoria`, `search_value`, `sort`, `dir`).
- Las actualizaciones vía AJAX (búsqueda con debounce, filtro de categoría, orden,
  paginación) sincronizan la URL visible del navegador.
- Atrás/Adelante después de cambiar filtros por AJAX es coherente: recupera el
  estado anterior y vuelve a pedir/renderizar los resultados correspondientes.
- Los 2 tests rojos de `TablaGoleadoresBusquedaTest` pasan a verde sin modificar
  sus aserciones existentes.

## 3. Non-goals (fuera de alcance explícito de esta unidad)

- **#102** (loading/error states, skeleton, mensaje de vacío con el término de
  búsqueda, `aria-live`) — unidad separada, toca los mismos archivos pero después.
- **#103** (dropdown de categoría accesible por teclado, `div` → `button`) — unidad
  separada; el dropdown actual (`div[data-id]`) se mantiene tal cual.
- **#104** (podio/jerarquía visual en mobile) — unidad separada.
- **Deuda de Pint preexistente** en `ControladorHome.php` (imports fully-qualified
  `\Illuminate\Support\Facades\DB::raw`, `\Carbon\Carbon`) — ya falla en `main`,
  no es una regresión de esta unidad y no se toca acá.
- **Normalización de `instancias-finales`** (`categoria`/`copa`/`fase` sin prefijo
  `id`) — surface distinta, fuera de esta unidad.
- **Rename de cualquier query param existente** — ver decisión en la sección 4.

## 4. Decisión: nombres de query param

**Se conservan los nombres actuales**: `idEdicion`, `idCategoria`, `sort`, `dir`,
`search_value`, `page`.

Justificación:
- `idEdicion`/`idCategoria`/`sort`/`dir` ya son el contrato estable en
  `tabla-vallas` (la referencia URL-first del propio repo), en `tabla-posiciones`
  y en `home`. Son legibles y coherentes con el naming legacy de la base
  (`idEdicion`, `idCategoria`).
- `welcome.blade.php` y `suscriptores-email.blade.php` ya generan enlaces
  públicos con `idEdicion` hacia estas vistas; renombrar rompería esos enlaces
  existentes sin necesidad.
- `search_value` es snake_case y desentona con el resto, pero ya es público (está
  en producción) y ya está asertado literalmente en el test rojo
  (`assertSee('search_value=ulises', false)`). Renombrarlo a algo como `q` no
  aporta nada al criterio de aceptación ("nombres estables y legibles" — ya son
  legibles) y sí obliga a tocar el test existente sin necesidad.
- El criterio de #101 pide "estables y legibles", no "consistentes entre sí".
  Ya lo son individualmente; no hay ambigüedad que resolver con un rename.

## 5. Alcance de archivos

| Archivo | Qué cambia |
|---|---|
| `app/Http/Controllers/ControladorHome.php` (método `tablaGoleadores`) | Elimina la clave de sesión `tabla_goleadores_filtros` y el `redirect()->route(...)`. Port del patrón de `tablaVallas`: todo el estado sale de `$request->query()`; `$goleadores_t->appends($appendParams)` con `array_filter([...])` sobre `idEdicion, idCategoria, search_value, sort, dir`. |
| `resources/views/layouts/tabla-goleadores.blade.php` | El JS `loadResults(params)` deja de mandar solo el parámetro que cambió: arma y manda el estado completo leído de un objeto de estado sincronizado con la URL actual. Se agrega sincronización de History API (ver sección 6). |
| `resources/views/partials/goleadores-resultados.blade.php` | Los links de orden usan `$queryBase = array_filter(request()->except(['page']))` + `array_merge($queryBase, ['sort' => ..., 'dir' => ...])`, igual que `tabla-vallas.blade.php:132-146`, para no perder `idEdicion`/`idCategoria`/`search_value`. |
| `tests/Feature/Panel/TablaGoleadoresBusquedaTest.php` | Sin tocar sus aserciones actuales (deben pasar a verde tal cual). Posible test adicional acotado si hace falta cubrir el `appends()` del paginador o el link de orden con estado completo. |

No se toca ningún archivo fuera de esta lista. No hace falta migración ni cambio de
esquema: es reordenar de dónde se lee el estado, no qué se calcula.

## 6. History API: decisión y trade-off

**Decisión: `pushState` en cada cambio de filtro/orden/página confirmado por AJAX,
más un listener `popstate` que vuelve a pedir resultados con los parámetros de la
URL restaurada.** No alcanza con `replaceState` solo.

Trade-off evaluado:
- `replaceState` (mutar la entrada actual sin apilar) es más simple, pero dejaría
  **una sola entrada de historial** para toda la sesión de filtrado. El criterio
  de #101 pide explícitamente "Atrás/Adelante funciona de manera coherente
  después de cambiar filtros": con solo `replaceState`, Atrás saltaría directo a
  la página anterior a la tabla de goleadores, saltándose todos los filtros
  intermedios — no es "coherente", es "no hay historial".
  Hoy `tabla-vallas` no tiene este problema porque **no usa AJAX**: cada cambio de
  filtro es una navegación completa (`window.location.href = newUrl` en
  `updateUrl()`), así que el navegador ya apila cada filtro por su cuenta. Como
  goleadores sí usa AJAX (búsqueda en vivo, orden, paginación sin recargar), no
  hay equivalente de historial gratis y hay que construirlo.
- `pushState` por cada cambio confirmado (categoría, orden, página, y término de
  búsqueda ya debounced) da un historial navegable: Atrás deshace el último
  cambio de filtro y dispara `popstate`, que vuelve a pedir el parcial con los
  query params restaurados — mismo mecanismo que un `GET` directo, solo que sin
  recarga completa.
- No hace falta ninguna librería: `history.pushState`/`window.onpopstate` son
  APIs nativas del navegador ya soportadas en todo el stack del proyecto (no hay
  `pushState` en el repo hoy, pero tampoco hace falta agregar dependencia
  alguna).
- El debounce de 350ms ya existente en la búsqueda evita que cada tecla genere una
  entrada de historial: el `pushState` se dispara una vez que `loadResults()`
  resuelve, no por cada `input`.

## 7. Estrategia de verificación

1. **Gate primario (red → green):**
   `docker compose exec app php artisan test --compact --filter=TablaGoleadoresBusqueda`
   Los 2 tests existentes deben pasar sin editar sus aserciones.
2. **Cobertura adicional** (solo si el gate primario no alcanza a probar
   `appends()`/`queryBase` en los links de orden): un test que arme la URL de un
   link de orden vía `assertSee` sobre un fragmento como
   `sort=equipo&amp;dir=asc&amp;idEdicion=` para confirmar que no se pierde el
   estado. No agregar más que esto — el resto del comportamiento (orden,
   paginación) ya está cubierto por el patrón espejo en `tabla-vallas`.
3. **Verificación manual sin cookies** (edición 10, la única local con datos
   joinables suficientes — ver trampa de datos en el explore):
   ```
   curl -s -o /dev/null -w '%{http_code}\n' \
     "http://localhost:PUERTO/tabla-de-goleadores?idEdicion=10"
   ```
   Debe devolver `200`, no `302`. Repetir con `idCategoria`, `sort`, `dir` y
   `search_value` combinados para confirmar que ninguno depende de sesión.
4. **Formato:** `docker compose exec app ./vendor/bin/pint --test` acotado a los
   archivos tocados (`--dirty` o ruta explícita), sin exigir que arregle la deuda
   preexistente de `ControladorHome.php` fuera del método tocado.

## 8. Riesgos

| Riesgo | Mitigación |
|---|---|
| `PlanillaPublicaTest` renderiza vistas con arrays de variables explícitos; una variable nueva y requerida en `tabla-goleadores.blade.php` o el parcial rompe ese test. | Esta unidad no agrega variables nuevas al `compact(...)` de la vista — reordena de dónde salen las mismas (`idEdicion`, `idCategoria`, `searchValue`, `sortKey`, `dir`). Si igual hiciera falta una, usar default a nivel Blade (`$var ?? valor`) en vez de asumir que el controlador siempre la manda. |
| El AJAX hoy manda solo el parámetro que cambió y confía en que el servidor mergee desde sesión; al eliminar sesión, un payload parcial resetea silenciosamente el resto de los filtros. | El JS arma el payload completo (estado actual + el campo que cambió) antes de cada `loadResults()`, igual que hace `tabla-vallas` al reconstruir la URL completa en `updateUrl()`. |
| `pushState` mal ubicado podría apilar una entrada por cada tecla de búsqueda. | Se dispara solo al resolver `loadResults()` (post-debounce), no en el handler de `input`. |
| Pint ya falla en `ControladorHome.php` por deuda preexistente ajena a esta unidad. | Verificar con `--dirty` o ruta explícita al método tocado; no se reporta como regresión de esta PR. |

## 9. Rollback

Cambio acotado a 1 método de controlador + 2 vistas + 1 test file, sin migraciones
ni cambios de esquema. Revertir es un `git revert` del commit/PR sin efectos
secundarios en otras superficies (ningún otro método del controlador ni otra vista
depende de `tabla_goleadores_filtros`).

## 10. Éxito

- Los 2 tests de `TablaGoleadoresBusquedaTest` en verde.
- `curl` sin cookies a `/tabla-de-goleadores?idEdicion=10` devuelve `200` con
  filas.
- Los criterios de aceptación de #96 y #101 (listados en el pedido) se cumplen
  manualmente: recarga, apertura en otro navegador, Atrás/Adelante coherente,
  búsqueda/orden/paginación por AJAX intactos, categoría funcionando.

## 11. Forecast de review workload

Estimado: **~150-220 líneas cambiadas** (incluye test), muy por debajo del umbral
de 400 líneas por PR:
- `ControladorHome::tablaGoleadores`: ~40-60 líneas (elimina el bloque de sesión/PRG,
  agrega `appends()` y filtrado).
- `tabla-goleadores.blade.php`: ~40-70 líneas (payload completo en `loadResults`,
  `pushState`/`popstate`).
- `goleadores-resultados.blade.php`: ~15-25 líneas (`$queryBase` en 4 links de
  orden).
- `TablaGoleadoresBusquedaTest.php`: 0-40 líneas (sin tocar los 2 tests existentes;
  suma opcional de un test acotado, ver sección 7.2).

Cabe holgado en un solo PR, sin necesidad de chained PRs.
