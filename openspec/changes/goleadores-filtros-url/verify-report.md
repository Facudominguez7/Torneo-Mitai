```yaml
schema: gentle-ai.verify-result/v1
evidence_revision: sha256:27a456709b21e6011dab8fed36d7aa63893e864659f9d1b9610681d900eb69c1
verdict: pass_with_warnings
blockers: 0
critical_findings: 0
requirements: 8/8
scenarios: 11/11
test_command: docker compose exec app php artisan test --compact tests/Feature/Panel/TablaGoleadoresBusquedaTest.php tests/Feature/Panel/TablaGoleadoresUrlFiltrosTest.php
test_exit_code: 0
test_output_hash: sha256:d6a80dad8cbd2034f55568f690549a02d35bf612f97bfa9cb50cacc3c37dabb7
build_command: docker compose exec app ./vendor/bin/pint --test tests/Feature/Panel/TablaGoleadoresUrlFiltrosTest.php resources/views/layouts/tabla-goleadores.blade.php resources/views/partials/goleadores-resultados.blade.php
build_exit_code: 0
build_output_hash: sha256:2669aac3f3cf276de5b35d8b0f17f1dcba5f468e5d5423fd1dbaace75d799905
```

# Verify Report — goleadores-filtros-url

**Veredicto: APROBADO CON OBSERVACIONES.** Cero regresiones, cero deuda de Pint nueva,
gate primario en verde, comportamiento URL-first confirmado end-to-end sin cookies.
Dos observaciones de severidad media (una funcional en `popstate`, una de cobertura en T5)
que no bloquean el merge pero deben quedar asentadas.

`skill_resolution: paths-injected` (`laravel-specialist` cargada desde
`/home/facundo/.agents/skills/laravel-specialist/SKILL.md` antes de trabajar).

---

## 1. Nota previa sobre el conteo de requirements

El pedido menciona "los 9 requirements" del spec. `specs/portal-publico/spec.md` contiene
**8 requirements y 11 scenarios**. El reporte se construye sobre los 8 reales.

---

## 2. Cobertura requirement → estado → evidencia

| # | Requirement | Estado | Evidencia |
|---|---|---|---|
| 1 | Resolución de estado desde la URL | **Satisfecho** | `ControladorHome.php:552` → `$filtros = $request->query();`. `grep -n "session\|redirect"` sobre el método (líneas 549-646) → 0 matches. 3 scenarios cubiertos por los 2 tests de `TablaGoleadoresBusquedaTest` (verdes) + tests 1 y 4 de `TablaGoleadoresUrlFiltrosTest`. |
| 2 | Ausencia de redirect en peticiones no-AJAX | **Satisfecho** | `curl` sin cookies a `?idEdicion=10`, `?idEdicion=10&sort=nombre&dir=asc` y `?idEdicion=10&search_value=a&page=2` → `200` con `redirect_url` vacío en los 3 casos. Bloque PRG eliminado por completo del diff. |
| 3 | Independencia de sesión | **Satisfecho (código) / sin cobertura automatizada** | Código: 0 lecturas/escrituras de sesión en el método. HTTP: request con cookie jar previamente contaminada (`?idEdicion=10&idCategoria=68&search_value=zzz&sort=equipo&dir=asc`) y luego `?idEdicion=10` con esa cookie → body **idéntico** al de un request cookieless (`diff` limpio ignorando `csrf-token`). **Pero ningún test automatizado lo ejercita** → ver Hallazgo M2. |
| 4 | Estado completo en links de orden | **Satisfecho** | `curl "?idEdicion=10&idCategoria=68&search_value=a&sort=nombre&dir=asc"` → los 4 hrefs de orden traen `idEdicion=10&idCategoria=68&search_value=a&sort=<col>&dir=<toggle>&page=1`. Reset de paginación correcto (`page=1`, nunca > 1). |
| 5 | Estado completo en links de paginación | **Satisfecho** | `appends($appendParams)` en `ControladorHome.php:615`. `curl "?idEdicion=10&sort=nombre&dir=asc&search_value=a"` → `...&search_value=a&sort=nombre&dir=asc&page=2..6`. Con categoría: `?idEdicion=10&idCategoria=68` → `...&idCategoria=68&sort=goles&dir=desc&page=2`. |
| 6 | Actualizaciones AJAX preservan el flujo sin recarga | **Satisfecho** | `curl -H 'X-Requested-With: XMLHttpRequest' "?idEdicion=10"` → `grep -c '<html'` = **0**, 30 filas en el parcial. Test 3 de `TablaGoleadoresUrlFiltrosTest` lo asserta. El contenedor reemplazado sigue siendo solo `#resultados-goleadores`; el input de búsqueda vive fuera → no pierde foco. |
| 7 | Sincronización de History API | **Satisfecho por la letra del spec, con observación** | `pushState` (1), `popstate` (2), `replaceState` (1) presentes en el JS servido por `curl`. Scenario "apila una entrada" → OK. Scenario "Atrás restaura el estado previo" → el `popstate` sí dispara la solicitud con los params de la URL restaurada y sí actualiza el contenedor, que es literalmente lo que el scenario exige. **Pero** los controles de filtro fuera del contenedor quedan desincronizados: ver Hallazgo M1, que aplica contra el criterio más amplio de #101, no contra el texto del scenario. Verificación: código + asset servido, **no** comportamiento observado en navegador. |
| 8 | No regresión de otras vistas públicas | **Satisfecho** | Suite completa comparada contra árbol limpio: **0 fallos introducidos** (sección 4). `curl` a `tabla-de-vallas`, `tabla-de-posiciones`, `fixture`, `instancias-finales` y `/` con `idEdicion=10` → `200` en las 5. |

**Totales: 8/8 requirements satisfechos, 11/11 scenarios satisfechos** — medidos contra el
texto literal del spec, que es la autoridad para el conteo.

Dos salvedades que el conteo no captura y que se reportan como warnings, no como gaps de spec:

- **R3** está satisfecho en código y comprobado por HTTP, pero sin test automatizado (M2).
- **R7** está satisfecho en su literal, pero con un desajuste de UI contra el criterio
  "Atrás/Adelante coherente" de #101 (M1), y su verificación es a nivel de código y de
  asset servido, no de comportamiento observado en un navegador real.

---

## 3. Task completion

`grep -cE "^\s*- \[x\]" tasks.md` → **6**. `grep -nE "^\s*- \[ \]" tasks.md` → **0 líneas sin marcar**.

No quedan tasks de implementación sin marcar. **Salvedad:** T5 está marcada `[x]` pero
su contenido no corresponde a lo especificado (Hallazgo M2). El checkbox es formalmente
correcto (se agregó un test file) pero materialmente incompleto.

---

## 4. Clasificación inequívoca de cada fallo de la suite

Método: `git stash push` acotado a los 3 archivos tracked del change + `rm` temporal del
test nuevo → árbol idéntico a `origin/main` (`git diff --stat origin/main` vacío) → suite
completa → `git stash pop` + restore. **Restauración verificada por fingerprint:**
diff sha256 `61ab9773...aef09` idéntico antes y después; md5 del test nuevo `f5ef0afd...` intacto.

| Árbol | Resultado |
|---|---|
| **Limpio (origin/main)** | `32 failed, 120 passed (890 assertions)` |
| **Con el change** | `30 failed, 126 passed (909 assertions)` |

**Fallos que solo existen en el árbol LIMPIO (arreglados por el change) — 2:**

```
FAILED  Tests\Feature\Panel\TablaGoleadoresBusquedaTest > it aplica busqueda case insensitive…
FAILED  Tests\Feature\Panel\TablaGoleadoresBusquedaTest > it encuentra un jugador fuera de la…
```

**Fallos que solo existen en el árbol CON EL CHANGE (regresiones introducidas) — 0:**

```
(comm -13 fails_clean.txt fails_dirty.txt → salida vacía)
```

Delta de conteo consistente: `126 - 120 = 6` = 2 tests reparados + 4 tests nuevos.

### `PlanillaPublicaTest` — veredicto pedido explícitamente

**PREEXISTENTE. Confirmado de forma independiente.** En el árbol limpio, sin ninguno de
los cambios de este change, falla idéntico:

```
FAILED  Tests\Feature\PlanillaPublicaTest > devuelve una planilla publica…
Failed asserting that two strings are identical.
-'18/03/2026 20:15'
+'18/03/2026 19:45'
at tests/Feature/PlanillaPublicaTest.php:177
  ➜ 177▕ ->assertJsonPath('data.ultima_actualizacion', '18/03/2026 20:15')
```

Mismo test, misma línea, mismo delta de 30 minutos (timezone) con y sin el change. **No es
regresión de esta unidad.** La afirmación de `apply-progress.md` es correcta.

### Los 29 fallos preexistentes restantes

Todos presentes en ambos árboles, ajenos al change: `Auth\*` (14, mayormente `419` CSRF /
`ModelNotFoundException`), `ProfileTest` (4, `419`), `Panel\AdminEquiposSinAsistenciaTest` (3),
`Panel\PlanillaConfirmacionTest` (3), `Panel\BuscarDniListaBuenaFeTest` (2, `QueryException`),
`Panel\ControladorEquipoEliminaTablaPosicionesTest` (2), `Panel\ValidadorReglasJugadorCategoriasTest` (1).

---

## 5. Formato (Pint)

Método: `pint --test -v` sobre `ControladorHome.php` + `resources/views` en ambos árboles,
extrayendo las líneas del diff propuesto y comparando con `comm`.

| Árbol | Líneas que Pint quiere tocar |
|---|---|
| Limpio | 31 |
| Con el change | 31 |

```
comm -13 pint_clean.txt pint_dirty.txt  →  (vacío)
```

**Cero quejas de Pint atribuibles a las líneas nuevas.** La corrección manual del parent
sobre `$appendParams` y el `compact()` del parcial AJAX se sostiene.

Gate acotado a superficies nuevas (`TablaGoleadoresUrlFiltrosTest.php` + las 2 vistas) →
`PASS`, exit 0.

Deuda **preexistente y ajena** confirmada intacta en `ControladorHome.php`:
`fully_qualified_strict_types` (`\Illuminate\Support\Facades\DB::raw` en líneas 611 y 619 —
líneas originales, no tocadas por el change; `\App\Models\Copa`, `\Carbon\Carbon`) y
`new_with_parentheses` (`new EstadisticasService()`). Ya fallaba idéntico en `origin/main`.
Correctamente no arreglada.

---

## 6. Rastro de sesión

```
grep -rn "tabla_goleadores_filtros" --include="*.php" --include="*.blade.php" .
```

Cero matches en código de aplicación. Los únicos hits son documentación
(`proposal.md`, `spec.md`, `tasks.md`, `explore.md` del change paraguas) y un snapshot
interno en `.git/gentle-ai/candidate-views/`. Sobre el método `tablaGoleadores`
(líneas 549-646): `grep -n "session\|redirect"` → **0 matches**. Ninguna otra parte del
repo leía esa clave.

---

## 7. Coherencia del JS reaplicado

**Diff sin ruido de indentación — confirmado.** `git diff --stat` y `git diff -w --stat`
dan resultados **idénticos** (37 líneas para el layout): no existe ni un solo hunk
whitespace-only. La restauración del parent se sostiene.

**Ninguna funcionalidad perdida en la reaplicación.** Inventario estructural
`origin/main` vs actual:

| Elemento | origin/main | actual |
|---|---|---|
| `addEventListener('click'` | 2 | 2 |
| `addEventListener('input'` | 1 | 1 |
| `addEventListener('submit'` | 1 | 1 |
| `addEventListener('popstate'` | 0 | **1** (nuevo) |
| `function loadResults` | 1 | 1 |
| `getElementById(...)` x4 | 4 | 4 |
| `querySelectorAll('.filtro-categoria [data-id]')` | 1 | 1 |

Único delta: el listener `popstate` agregado. Nada eliminado.

**Análisis punto por punto del pedido:**

- **`loadResults` arma el payload completo desde la URL** — Correcto
  (`layouts/tabla-goleadores.blade.php:142-152`): parte de
  `new URLSearchParams(window.location.search)` y hace `set`/`delete` encima. Valores
  vacíos (`''`, `null`, `undefined`) se borran del query, lo que hace que
  "Todas las categorías" (`data-id=""`) y limpiar el buscador funcionen correctamente.
- **`pushState` no se dispara en `popstate`** — Correcto: la rama `opciones.desdeHistorial`
  usa `replaceState`. No hay bucle ni entradas duplicadas por navegación de historial.
- **Debounce no apila una entrada por tecla** — Correcto: el `pushState` vive dentro de
  `loadResults`, que solo se invoca desde el `setTimeout` de 350 ms
  (`layouts/tabla-goleadores.blade.php:190-193`), no desde el handler de `input`.
- **Interceptor de links de orden/paginación** — Sigue funcionando
  (`layouts/tabla-goleadores.blade.php:211-224`): delegación desde `#resultados-goleadores`
  con `closest('a.orden-goleadores, .pagination a, nav a')`, `preventDefault()` y merge de
  los params del href. Como ahora los hrefs server-side ya traen estado completo, el merge
  es consistente.

---

## 8. Criterios de aceptación de las issues

### #96

| Criterio | Estado | Evidencia |
|---|---|---|
| URL con `idEdicion` en sesión nueva funciona | OK | `curl` cookieless `?idEdicion=10` → `200`, 30 filas |
| Recarga conserva estado | OK | Estado 100% en query string; render determinístico (2 requests idénticos → mismo body salvo `csrf-token`) |
| Enlace reproducible en otro navegador | OK | Sin dependencia de cookie; body idéntico con y sin sesión previa |
| Categoría sigue funcionando | OK | `?idEdicion=10&idCategoria=68` filtra y pagina correctamente |
| Búsqueda/orden/paginación por AJAX | OK | Parcial AJAX sin `<html>`, 30 filas; handlers intactos |
| No se rompen otras vistas públicas | OK | 0 regresiones en suite + `200` en las 5 rutas públicas |
| No requiere cookie previa | OK | Todos los `curl` de este reporte son cookieless |

### #101

| Criterio | Estado | Evidencia |
|---|---|---|
| URL filtrada reproducible en incógnito | OK | Body idéntico entre requests cookieless repetidos |
| Atrás/Adelante coherente | **Parcial** | Resultados y URL sí se restauran; los controles de filtro no. Hallazgo M1. Único criterio de issue no cumplido del todo |
| Recarga no pierde filtros | OK | Estado en URL |
| Paginación y orden por AJAX | OK | Interceptor + `pushState` verificados |
| Nombres de param estables | OK | `idEdicion`, `idCategoria`, `sort`, `dir`, `search_value`, `page` sin renombrar; decisión justificada en `proposal.md §4` |
| Sin mezcla de estado entre pestañas vía sesión | OK | Cero sesión en el método; comprobado por HTTP con cookie contaminada |

---

## 9. Hallazgos por severidad

### M1 — MEDIA (funcional). `popstate` no resincroniza los controles de filtro

**Ubicación:** `resources/views/layouts/tabla-goleadores.blade.php:226-229` (listener) en
combinación con `:96-111` (form/buscador) y `:61-70` (label de categoría).

`loadResults()` solo reemplaza `contenedor.innerHTML`, y `contenedor` es
`#resultados-goleadores` (línea 115). Tanto `#buscador-goleadores` (línea 107) como
`#label-categoria` (línea 66) están **fuera** de ese nodo. Consecuencia observable:

1. El usuario escribe "perez" → debounce → `pushState`.
2. Presiona Atrás → `popstate` → la tabla vuelve a la lista sin filtrar (correcto), la URL
   vuelve a no tener `search_value` (correcto), **pero el input sigue mostrando "perez"**.

Lo mismo con el label de categoría. La UI queda contradiciendo a la URL y a los resultados
visibles.

**Delimitación precisa:** la letra del spec se cumple — el scenario exige que `popstate`
"dispara una solicitud de resultados con los parámetros de la URL restaurada y el contenedor
de resultados se actualiza en consecuencia", y eso ocurre. Lo que no se cumple es el
criterio más amplio de #101, "Atrás/Adelante funciona de manera coherente". Por eso este
hallazgo se reporta como warning y no reduce el conteo de scenarios.

No bloqueante: el estado canónico (URL + resultados) es correcto y compartible. Es un
desajuste de presentación de los controles.

### M2 — MEDIA (cobertura / fidelidad del artefacto). T5 no implementó el test que especifica

**Ubicación:** `tasks.md:48` (T5, marcada `[x]`) vs `tests/Feature/Panel/TablaGoleadoresUrlFiltrosTest.php`.

T5 pide textualmente **un** test de *independencia de sesión*: cliente B con
`$this->withSession(['tabla_goleadores_filtros' => [...otros filtros...]])` haciendo el
mismo GET que el cliente A, y ambas respuestas con el mismo goleador. Razón declarada en la
propia task: *"cubre el scenario 'Mismo query, con y sin sesión previa' del spec, que ningún
test existente ejercita"*.

Lo implementado son **4 tests distintos**, ninguno de los cuales toca sesión:

```
grep -n "withSession\|session(" tests/Feature/Panel/TablaGoleadores*.php
→ (sin resultados)
```

Consecuencia: el **único requirement del spec que no tenía cobertura previa sigue sin
cobertura automatizada**. Solo está verificado manualmente (sección 2, fila 3).

Además, el **criterio de triangulación** de T5 ("el test nuevo debe fallar si se reintroduce
lectura de sesión en `tablaGoleadores`") se cumple solo a medias:

- Si se reintrodujera el `redirect()` del patrón PRG → el test 1 fallaría (esperaría `200`,
  recibiría `302`). **Protegido.**
- Si se reintrodujera precedencia de sesión sin redirect, p. ej.
  `$filtros = array_merge($request->query(), session('tabla_goleadores_filtros', []));`
  → los 4 tests nuevos **siguen en verde**, porque ninguno construye una sesión previa.
  **Sin protección.**

`apply-progress.md` (desvío #2) justifica únicamente el cambio de *ubicación* del test
(archivo nuevo con helpers renombrados), no la desaparición de su *contenido*. El artefacto
afirma "TRIANGULATE" pero lo que se agregó es cobertura de casos ya cubiertos por otra vía,
no triangulación del riesgo específico. Esto es exactamente el patrón de sobre-afirmación
que ya apareció con Pint.

### L1 — BAJA. `replaceState` en la rama de historial es un no-op

**Ubicación:** `resources/views/layouts/tabla-goleadores.blade.php:171`.

`destino` se construye a partir de `window.location.search`, que en el momento en que corre
el handler de `popstate` **ya es** la URL restaurada por el navegador. El
`history.replaceState(null, '', destino)` reemplaza la entrada por sí misma. Sin efecto
observable; código muerto que sugiere una intención que no se está cumpliendo.

### L2 — BAJA. `pushState` sin deduplicación de URL

**Ubicación:** `resources/views/layouts/tabla-goleadores.blade.php:173`.

No hay comparación contra `window.location.href` antes de apilar. Si dos resoluciones
consecutivas del debounce producen la misma query (escribir un carácter y borrarlo con más
de 350 ms de por medio), se apilan dos entradas idénticas y un Atrás parece "no hacer nada".
Bajo impacto; una guarda `if (destino !== window.location.href)` lo resolvería.

### L3 — BAJA. `array_filter` sin callback en `$appendParams`

**Ubicación:** `app/Http/Controllers/ControladorHome.php:607-613`.

`array_filter()` sin callback descarta todo valor *falsy*, no solo `null`/`''`. Hoy es
inocuo (los IDs son `> 0`, y `sort`/`dir` siempre traen valor normalizado, por lo que el
paginador siempre emite `sort` y `dir` aunque el usuario no los haya pedido). Pero un
`idCategoria` con valor `"0"` desaparecería silenciosamente de los links del paginador.
Mismo patrón en `$queryBase` (`partials/goleadores-resultados.blade.php:7-11`).
Nota preventiva, no defecto actual.

### I1 — INFO. Aserción indirecta en el test de links de orden

**Ubicación:** `tests/Feature/Panel/TablaGoleadoresUrlFiltrosTest.php:76-95`.

`assertSee('idEdicion='.$id, false)` y `assertSee('search_value=diaz', false)` se evalúan
sobre la página completa, no sobre el `href` del link de orden. No verifican que ambos
params estén en el **mismo** href, no cubren `idCategoria`, y no asertan el
"no incluye un `page` mayor a 1" del scenario. En este escenario concreto no hay falso
positivo (con 1 solo goleador el paginador no renderiza y el `input hidden` emite
`name="idEdicion" value="10"`, no `idEdicion=10`), así que el test es válido — pero es más
débil de lo que el requirement 4 pide. La funcionalidad sí está bien: verificada
directamente por inspección de los 4 hrefs vía `curl` (sección 2, fila 4).

### I2 — INFO. Discrepancia de conteo de requirements

El pedido habla de 9 requirements; el spec tiene 8 (con 11 scenarios).

### I3 — INFO. Leve desvío del forecast de review workload

`tasks.md` proyectó T5 en 0-40 líneas; el test file real son 127. Total 232 vs forecast
150-220. Irrelevante contra el umbral de 400 (sección 10).

---

## 10. Review workload

| Métrica | Valor |
|---|---|
| Tracked (`git diff --stat origin/main`) | 61 inserciones, 44 borrados = **105 líneas** |
| Test nuevo (untracked) | **127 líneas** |
| **Total** | **232 líneas** |
| Umbral | 400 |
| `git diff` vs `git diff -w` | **Idénticos** (0 líneas de ruido de indentación) |

Entra holgado en un solo PR. `Chained PRs recommended: No` respetado. Sin scope creep: los
3 archivos tocados son exactamente los declarados en `proposal.md §5`, y el cambio en
`ControladorHome.php` está confinado al método `tablaGoleadores`.

---

## 11. Auditoría de `apply-progress.md` contra la realidad

| Afirmación del artefacto | Verificación independiente |
|---|---|
| Pint: las líneas nuevas no generan issues | **CONFIRMADO** hoy (`comm` de diffs de Pint clean vs dirty → vacío) |
| Deuda de Pint de `ControladorHome.php` es preexistente | **CONFIRMADO** vía stash |
| `PlanillaPublicaTest` falla por timezone, preexistente | **CONFIRMADO** vía stash, mismo test / línea / delta |
| `curl ?idEdicion=10` → `200`, 30 filas | **CONFIRMADO** |
| `pushState`/`popstate` presentes en el JS servido | **CONFIRMADO** |
| AJAX devuelve parcial sin `<html>` | **CONFIRMADO** |
| Gate primario 2 tests en verde | **CONFIRMADO** |
| T5 = TRIANGULATE de independencia de sesión | **NO SE SOSTIENE** — ver Hallazgo M2 |
| "Tareas restantes: Ninguna" | Formalmente sí (0 `- [ ]`); materialmente T5 quedó incompleta |

---

## 12. Qué queda cubierto solo por verificación manual

1. **`pushState` real en navegador** — que la URL visible cambie sin recarga de documento y
   que se apile una entrada nueva. `curl` solo prueba que el código está servido.
2. **`popstate` real** — que Atrás dispare el re-fetch y repinte el contenedor.
3. **Conservación del foco del input de búsqueda** durante el reemplazo del parcial
   (requirement 6). Estructuralmente garantizado (el input está fuera del nodo reemplazado)
   pero no ejercitado.
4. **Independencia de sesión** — cubierto por `curl` en este reporte, pero **sin test
   automatizado** (Hallazgo M2). Es el gap de cobertura más relevante.
5. **Una sola entrada de historial por búsqueda debounced** — requiere DevTools.
6. **Ventana de incógnito real** — aproximado con `curl` cookieless.

Motivo estructural: JS vanilla en Blade sin runner de browser, y la restricción del change
prohíbe sumar dependencias (Playwright/Cypress quedarían fuera de alcance). Los puntos 1, 2,
3, 5 y 6 son aceptables como manuales. El punto 4 **no**: es un test de Pest trivial que la
propia T5 ya especificaba.

---

## 13. Bloqueantes

**Ninguno.** El change es mergeable.

Recomendación previa al merge (no bloqueante, orden de prioridad):

1. Agregar el test de independencia de sesión que T5 especificaba (M2) — ~15 líneas de Pest,
   cierra el único gap de cobertura del spec.
2. Resincronizar `#buscador-goleadores` y `#label-categoria` en el handler de `popstate` (M1).
3. Guarda de deduplicación en `pushState` y eliminación del `replaceState` muerto (L1, L2).
