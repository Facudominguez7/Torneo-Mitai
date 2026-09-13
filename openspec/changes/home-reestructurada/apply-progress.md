# Apply Progress — `home-reestructurada` (issue #100)

Estado: **implementación completa (T1→T11 + enmienda de categoría)**. Sin commits
(pedido explícito del orquestador). Suite completa con **cero fallos nuevos**
(30F preexistentes en `main`, idénticos test por test).

## Consumo de estado estructurado

- Estado nativo recibido con `applyState: blocked` por "selección ambigua"; el
  prompt del orquestador desambiga explícitamente el change (`home-reestructurada`)
  y define superficies de edición permitidas, por lo que se resolvió el blocker
  y se procedió. `actionContext.mode: repo-local`, root autorizado OK.
- Artefactos leídos de `openspec/changes/home-reestructurada/`: `tasks.md`
  (autoridad fina), `proposal.md`, `specs/portal-publico/spec.md` (8 requirements
  + enmienda). No existe `design.md` ni `openspec/config.yaml`; el contenido de
  diseño vive en proposal+tasks y la disciplina RED→GREEN→TRIANGULATE vino de
  tasks.md (TDD estricto por contrato del prompt, no por config).
- Gate de workload: `Decision needed: Yes` + `Chained PRs: Yes` → el orquestador
  entregó la ruta resuelta (PR1 mecánica + PR2 apilada, sin commit). Sin excepción
  `size:exception` pedida ni otorgada antes de escribir código.

## PR1 — Extracción `<x-tarjeta-partido>` (T1-T4) — boundary de PR

Archivos: `resources/views/components/tarjeta-partido.blade.php` (NUEVO, 143
líneas), `resources/views/layouts/fixture.blade.php` (+2/−226).

- **T1 baseline**: 5 capturas Playwright antes (desktop 1280 + mobile 390; default
  `?idEdicion=10`, `idFecha=todas`, e interzonal `?idEdicion=8&idFecha=todas`)
  en `/tmp/pr1_antes_*.png` + HTML de tarjetas guardado (`/tmp/pr1_antes_articulos_ed10.html`
  137 tarjetas, `_ed8.html` 36). Nota: `/fixture` sin `idEdicion` muestra el vacío
  instructivo (comportamiento existente), por eso las capturas fijan edición.
- **T2 caracterización** (`HomeReestructuradaTest::fixture`): verde ANTES del
  refactor (caracterización, no RED clásico — documentado en el propio test).
  Ajuste a la realidad: un pendiente con horario futuro renderiza pill "Próximo",
  no "Pendiente". Cubre estándar (equipos, marcador `2 - 1`, data-jugado) e
  interzonal (badge + `via-amber-900/30`).
- **T3**: componente anónimo con `@props(['partido', 'interzonal' => false])`;
  las 8 diferencias entre variantes (borde/gradiente/sombra de tarjeta, clases de
  estado, punto de estado, borde de escudos, color del reloj, clases del botón
  planilla) quedan como variables con strings EXACTOS para producir HTML
  byte-idéntico; badge "Interzonal" condicionado por `@if`. Reemplazo mecánico de
  los 2 bloques inline (`fixture.blade.php` 605→377 líneas).
- **T4 TRIANGULATE**: comparación normalizada de espacios de las 173 tarjetas
  (137 ed10 + 36 ed8, ambas variantes) antes/después → **0 diferencias**. Suite
  Fixture: 33 passed. Capturas después (`/tmp/pr1_despues_*.png`) revisadas con
  `read`: card interzonal con gradiente ámbar + badge + botón ámbar idénticos.
  Sin archivos PHP en PR1 → pint n/a.

## PR2 — Home reestructurada + enmienda (T5-T8) — boundary de PR

Archivos: `welcome.blade.php` (+159/−263), `welcome.js` (+20/−10),
`ControladorHome.php` (+56/−8), `ControladorEquipoSeguido.php` (+3),
`ShowRequest.php` (+2), `EquipoSeguidoService.php` (+7/−3),
`EquipoSeguidoTest.php` (+60), `HomeReestructuradaTest.php` (NUEVO, 349).

- **T5 RED**: 6 tests del bloque vivo; 5 rojos por las razones correctas
  (etiquetas ausentes, partidos no listados, query a `users` presente, chips
  ausentes). El test "no rompe sin partidos" ya pasaba (guarda).
- **T6 GREEN**: `ControladorHome::index()` reutiliza
  `FixtureExploradorService::resolverFechaRelevante()` (instancia local, mismo
  patrón que `fixture()` en el propio archivo — desvío menor documentado abajo),
  agrega `$idFechaRelevante/$fechaRelevante/$partidosFechaRelevante/
  $hayPartidosFuturos`, y elimina `$roles = User::pluck('rol')`, `$idCategoria`
  y el import de `User`. Equipos con `nombreCategoria` vía `with('categoria')`.
- **T7 RED**: jerarquía (orden de 6 marcadores), prosa eliminada (27 textos),
  1 solo link a fixture fuera de nav, guest/admin equivalentes. Helper propio
  `ordenEnHtml()` (los strings de Pest no tienen `toBeSeeInOrder`; normaliza
  whitespace como `assertSee`).
- **T8 GREEN**: welcome reescrita con la jerarquía de 9 bloques: alertas → hero
  mínimo (h1=edición, selector, CTA "Ver partidos" + CTA "Seguir mi equipo") →
  `#fecha-relevante` (etiqueta dinámica, fecha completa sin recorte, link único
  a fixture, `x-data="crearPlanillaPublica()"` + `<x-planilla-publica-modal />`
  para que "Ver planilla" funcione en la home) → `#seguir-equipo-bloque`
  **trasladado intacto** (mismos ids/JSON/contrato welcome.js) → 4 chips
  (`id="accesos-secundarios"`, Vallas 4to) → carrusel sin kicker/prosa ni "Ver
  todo el fixture" → historial a links (icono+título+"Ver más") → comunidad (h2
  + 3 links) + institucional recortado a los 4 bullets (kicker/h3/p/logo fuera,
  bullets conservados por la regla ask-on-risk: nada se elimina sin confirmación
  explícita) → footer sin cambios. Sin `<nav>`/`aria-current` nuevos.

## Enmienda de categoría (ubicación: PR2, backend + frontend)

Documentada como enmienda del owner (spec `Requirement: Selección de equipo
consciente de categoría`):

- `ShowRequest`: `idCategoria => nullable|integer|exists:categorias,id`.
- `EquipoSeguidoService::resolver(nombre, idEdicion, idCategoria = null)`:
  filtra por categoría cuando viene; sin ella, primer match por id
  (retrocompatible). `ControladorEquipoSeguido` pasa
  `$request->validated('idCategoria')`.
- `ControladorHome::index()` envía los equipos con `id`/`nombre`/`idCategoria`/
  `nombreCategoria` (el JSON `#seguir-equipo-datos` cambia de forma: ahora son
  arrays tipados; welcome.js es la única consumidora).
- `welcome.js`: opciones "Nombre — Categoría", persistencia
  `{nombre, idCategoria}` (favoritos viejos `{nombre}` siguen funcionando),
  fetch con `idCategoria` solo cuando existe.
- **Tests de #99**: ninguno asumía primer-match CON categoría → no se modificó
  ningún test preexistente; se agregaron 3 (desambigue con homónimos reales
  "Deportivo Misiones "Azul"", retrocompatibilidad sin idCategoria, 422 con
  categoría inexistente). EquipoSeguido: 12 passed.

## T9 — TRIANGULATE + suite completa

`php artisan test --compact`: **30 failed / 184 passed**. Comparado contra
`main` con stash temporal (incluye untracked): **mismos 30 fallos, idénticos
test por test** (Auth 14, Profile 4, Panel 8, PlanillaPublica 1, Registration 1,
Panel extras 2). Cero fallos introducidos. Pint por archivo tocado: PASS en
Service, ShowRequest, ControladorEquipoSeguido, ambos tests;
`ControladorHome.php` conserva SOLO la deuda preexistente de main (el diff de
pint de main tenía 20 líneas problemáticas, el actual 12 — mi rewrite no agregó
deuda y redujo instancias; NO se "arregló" el archivo completo por consigna).

## T10 — Verificación visual Playwright (AGENTS.md §8)

Script `~/.local/mitai-playwright/home-reestructurada-check.js` (toolbox fuera
del repo, Chrome del sistema). Evidencia:

- Jerarquía DOM: `#fecha-relevante` → `#seguir-equipo-bloque` →
  `#accesos-secundarios` en orden ✓; bloque vivo visible ✓.
- Camino real con datos de producción (ed 10): pill cruda "Fecha pendiente"
  (uppercase solo visual), fecha "6° Fecha", **28 tarjetas** (fecha completa,
  sin recorte) ✓; 1 único link a fixture en el bloque ✓.
- Nav sticky (#97): `position: sticky` + `.nav-compacta` al scrollear ✓
  (desktop y mobile, capturas `/tmp/t10_home_scrolled_*.png`).
- Capturas revisadas con `read`: `/tmp/t10_home_mobile.png` (390px: primera
  pantalla = hero + bloque vivo visible, cero prosa), `/tmp/t10_home_desktop.png`,
  `/tmp/t10_seguir_equipo_card.png`.
- Flujo seguir-equipo con categoría (enmienda, extremo a extremo): el selector
  lista 8 opciones "Nombre — Categoría" de los homónimos reales; al elegir
  `Deportivo Misiones "Azul" — Categoria 2014` se llama
  `/equipo-seguido?...&idCategoria=73`, la card hidrata ESE equipo (último
  resultado vs Los Panteras 4-1), localStorage queda
  `{"nombre":"Deportivo Misiones \"Azul\"","idCategoria":73}` y tras recarga
  re-hidrata igual (persistencia + retrocompatibilidad visual).

## T11 — Forecast real de líneas por PR

| PR | Archivos | Brutas | Netas |
| --- | --- | --- | --- |
| PR1 | componente nuevo (143) + fixture (+2/−226) | **371** | −81 |
| PR2 backend | ControladorHome (+56/−8) + Service (+7/−3) + ShowRequest (+2) + ControladorEquipoSeguido (+3) + EquipoSeguidoTest (+60) | 131 | +117 |
| PR2 vista | welcome (+159/−263) + welcome.js (+20/−10) + HomeReestructuradaTest nuevo (349) | 801 | +252 |

Total change: **~1311 brutas / +317 netas** (forecast de tasks.md: ~380-450
brutas — quedó por encima por el volumen real de tests que exige el spec: 27
textos prohibidos + orden + etiquetas + desambigue, y la reescritura bruta de
welcome). PR1 entra en budget. PR2 como unidad única NO entra (940 brutas con
tests): división recomendada si se quiere respetar el umbral → PR2a backend
(131, entra) + PR2b vista+tests (801, excede aunque el propio test nuevo son
349 líneas que acompañan la revisión; no se puede recortar sin bajar cobertura
que el spec exige literalmente). Decisión de entrega: **ask-on-risk** (pendiente
del dueño); no se inventó chain strategy ni exception.

## Desvíos y decisiones (justificados)

1. **Tests en un solo archivo** `HomeReestructuradaTest.php` (tasks preveían
   `FixtureRenderizaTarjetasTest` + `HomeFechaRelevanteTest` +
   `HomeJerarquiaTest`): las superficies permitidas por el orquestador solo
   incluyen `HomeReestructuradaTest.php` + `EquipoSeguidoTest.php`. Agrupado
   con `describe()` por fase.
2. **Capturas en /tmp** y no `docs/baseline-*.png` (tasks): `docs/` no es
   superficie permitida y AGENTS.md prohíbe borrar/crear auxiliares sin
   instrucción; /tmp + rutas en este archivo preservan la evidencia.
3. **Instancia local del servicio** en lugar de inyección por constructor
   (tasks T6 decía "inyectar... u omitir"): `fixture()` del mismo controlador ya
   instanciaba `new FixtureExploradorService()`; se siguió el precedente del
   archivo para minimizar diff (el constructor no existía).
4. **`x-data` + modal en el bloque vivo**: el botón "Ver planilla" de la tarjeta
   compartida depende de `crearPlanillaPublica()` (scope Alpine); sin envoltorio
   quedaría un botón muerto en la home. Envoltorio acotado al bloque vivo.
5. **Caracterización con pill "Próximo"**: el test T2 se ajustó al HTML real
   (pendiente futuro ≠ "Pendiente"), según la consigna de tasks T2.
6. **Deuda pint de ControladorHome**: sin `--test` global ni arreglos fuera de
   mis líneas; verificado que el diff de deuda bajó (20→12 instancias) y no subió.

## Split de tests para entrega en 3 PRs apiladas (decisión del dueño, post-T11)

El dueño definió la entrega en 3 PRs apiladas (PR1 extracción del componente,
PR2 backend categoría, PR3 home vista) y pidió separar los tests por frontera
de PR. Se partió `HomeReestructuradaTest.php` en dos archivos sin commits y sin
cambiar asserts (solo movimientos + renombres de helpers):

- `tests/Feature/TarjetaPartidoTest.php` (NUEVO): los 2 tests de caracterización
  del fixture (`describe 'fixture: tarjeta de partido'`). Helpers duplicados con
  prefijo propio `tarjetaBase/tarjetaFecha/tarjetaPartido` (las funciones Pest
  son globales entre archivos del mismo run: "Cannot redeclare"). Ambos tests
  son **PR1-safe**: solo ejercitan `GET /fixture` (`ControladorHome::fixture()`,
  sin cambios desde main), `fixture.blade.php` y el componente; no pasan por la
  home ni por `index()`.
- `tests/Feature/HomeReestructuradaTest.php`: quedan los 10 tests de la home
  (bloque vivo: 6, jerarquía/prosa/accesos: 4) con los helpers `home*` y
  `ordenEnHtml`.

Esto reemplaza el desvío 1 ("tests en un solo archivo"): el agrupamiento ahora
sigue la frontera de PR que pide el dueño. Verificación del estado final (PR3):
TarjetaPartido+Home 12 passed (53 asserts), FixtureExplorador 8 passed (sin
regresión), `pint --test` PASS (2 archivos).

## Tareas restantes

Ninguna de implementación (T1-T11 completas). Pendientes de FASES POSTERIORES
(no son de apply): `sdd-verify` (suite y verificación independiente),
`sdd-sync`, archivado, y la decisión de entrega/PR (`ask-on-risk` del dueño).
Commits sugeridos (ya definidos en tasks.md T11) quedan para el orquestador —
NO se hizo commit por consigna explícita.
