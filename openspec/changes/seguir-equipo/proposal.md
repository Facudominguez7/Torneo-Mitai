# Propuesta — Seguir mi equipo (#99)

Fase: SDD proposal. Basada en `openspec/changes/seguir-equipo/explore.md` (leído completo) y en las decisiones ya cerradas por el usuario (ver sección 0). No se implementa código en esta fase.

## 0. Decisiones ya cerradas (no se reabren)

1. Identidad del favorito = **nombre** del equipo en `localStorage` (no `id`, porque el mismo club cambia de `id` por edición). Clave: `mitai_equipo_seguido`, valor `{"nombre": "..."}`.
2. Endpoint público JSON `GET /equipo-seguido?nombre=&idEdicion=` (param `idEdicion`, no `edicion` — convención legacy del repo). Resuelve el equipo en esa edición con matching tolerante al mutator `setNombreAttribute`.
3. Selector client-side en la home sobre los equipos de la edición activa (37 filas, los pasa el controlador).
4. Card renderizada por JS vanilla con `fetch` (sin recarga, sin cookie, sin login).
5. Sin datos personales en `localStorage` (solo el nombre del equipo).

Datos ya verificados (no se re-derivan): edición 10 tiene 137/137 partidos con `horario_datetime` (100%); `horario_datetime` no tiene cast en `Partido` (sale como string); último partido real verificado (`#2036`, 2-6); próximo puede ser `null` para categorías finalizadas; `equipos` no tiene `institucion_id` directo pero sí `idInstitucion` vía hook (no se usa como fallback en esta iteración — nombre es la única clave); `/fixture` ya soporta `?equipo=` con LIKE escapado.

## 1. Intent

Cerrar #99: hoy un visitante que sigue a un equipo puntual no tiene forma de ver rápido "¿cuándo juega?" o "¿cómo salió?" sin buscarlo manualmente en el fixture completo. La propuesta agrega, en la home pública, un selector de equipo de la edición activa que persiste la elección en `localStorage` (sin login) y una card que muestra próximo partido / último resultado + accesos directos a fixture y tabla de posiciones de ese equipo, con manejo explícito del caso "el equipo no participa en esta edición" (favorito de una edición anterior).

No se toca el sistema de autenticación, no se persiste el favorito server-side, no se rediseña la home (#100 es otro issue).

## 2. Decisiones técnicas (con justificación)

### 2.1 Contract del endpoint

`GET /equipo-seguido?nombre={string}&idEdicion={int}` → `ControladorEquipoSeguido::show()`. Siempre responde **200** (el caso "no disponible" es un estado esperado del flujo — cambio de edición —, no un error HTTP; sigue el precedente de `ControladorPlanillaPublica` para reservar 404/500 a fallos reales de datos/infra):

```json
{
  "disponible": true,
  "equipo": { "nombre": "Amigos F.c.", "idCategoria": 3 },
  "proximo": { "rival": "Boca Chicos", "esLocal": true, "fecha_nombre": "Fecha 5", "horario": "Sáb 14/09 · 15:30", "cancha": "Cancha 2" },
  "ultimo": { "rival": "River Junior", "esLocal": false, "goles_favor": 2, "goles_contra": 6 },
  "links": { "fixture": "/fixture?idEdicion=10&equipo=Amigos%20F.c.", "tabla": "/tabla-de-posiciones?idEdicion=10&idCategoria=3&idGrupo=5" }
}
```

`{"disponible": false}` cuando el nombre no resuelve un equipo en `idEdicion`. `proximo`/`ultimo` son `null` de forma independiente (un equipo puede tener último sin próximo, o viceversa si es su debut).

Validación vía `App\Http\Requests\EquipoSeguido\ShowRequest` (FormRequest, convención del proyecto para toda validación de request): `nombre` → `required|string|max:50` (mismo límite que `equipos.nombre`), `idEdicion` → `required|integer|exists:ediciones,id`. Sin auth, sin throttle en esta iteración — riesgo documentado en §7 (query indexada y barata: `where('idEdicion', $id)` + `LIKE`/igualdad sobre `nombre` con índice compuesto existente sobre `idEdicion`).

### 2.2 Resolución del equipo por nombre

Comparación tolerante al mutator: normalizar el `nombre` de entrada igual que `setNombreAttribute` (colapsar espacios + Title Case mbstring) antes del `where`, o comparar en SQL con `whereRaw('LOWER(nombre) = ?', [Str::lower(...)])` sobre el valor ya colapsado. Se prefiere **normalizar en PHP con la misma función del mutator** (extraída a un método estático reusable si no lo es ya) para que resolución y persistencia compartan una sola fuente de verdad, en vez de duplicar la regla en SQL.

**Riesgo de unicidad conocido y no bloqueante**: `equipos.nombre` es `string(50)` sin índice único; dos equipos de la misma edición en categorías distintas podrían compartir nombre. El servicio toma el primer match (`orderBy('id')->first()`) de forma determinística. No se persiste `idCategoria` en `localStorage` (decisión cerrada §0.1 solo guarda nombre) — se documenta como limitación conocida en vez de bloquear el change, porque no hay evidencia de colisiones reales y el impacto es "ver el equipo equivocado del mismo nombre", no un error.

### 2.3 Próximo / último partido

Reutiliza tal cual la semántica ya validada en `FixtureExploradorService::resolverFechaRelevante()` (PR #116), agregando el filtro por equipo agrupado en closure:

```php
Partido::query()
    ->where('idEdicion', $idEdicion)
    ->where(fn ($q) => $q->where('idEquipoLocal', $equipo->id)->orWhere('idEquipoVisitante', $equipo->id))
    ->where(fn ($q) => $q->where('jugado', 0)->orWhereNull('jugado'))
    ->whereNotNull('horario_datetime')
    ->where('horario_datetime', '>=', now())
    ->orderBy('horario_datetime')->orderBy('idFechas')
    ->with(['fecha'])
    ->first();
```

Simétrico para "último" (`jugado = 1`, `orderByDesc`). `->with('fecha')` evita el N+1 del nombre de fecha. El rival se resuelve sin cargar el modelo completo del otro equipo: basta `SELECT nombre` del lado contrario (`idEquipoLocal`/`idEquipoVisitante` según corresponda), vía `Equipo::query()->whereIn('id', [...])->pluck('nombre', 'id')` en una sola consulta batched si hubiera múltiples partidos a resolver (acá es 1 próximo + 1 último → 2 lookups como máximo, sin N+1 real).

`horario` en el JSON sale **formateado explícitamente** dentro del servicio (`Carbon::parse($partido->horario_datetime)->translatedFormat('D d/m · H:i')` o equivalente) — no se agrega cast a `Partido` (modelo compartido con fixture/admin/planillas; tocarlo es un riesgo de regresión fuera de alcance de este change).

### 2.4 Links de fixture y tabla

- **Fixture**: `/fixture?idEdicion={id}&equipo={nombre}` — ya funciona (`ControladorHome::fixture()` línea 486, LIKE escapado). Cero código nuevo del lado fixture.
- **Tabla de posiciones**: `/tabla-de-posiciones?idEdicion={id}&idCategoria={idCategoria}` con `idGrupo` opcional cuando se puede resolver. Fuente de `idGrupo`, en orden de prioridad:
  1. `partidos.idGrupo` del partido resuelto (próximo o último, lo que haya) — costo cero, ya está en el mismo query.
  2. Si no hay próximo ni último (equipo sin partidos jugados/programados), fallback al pivot `equipos_grupos` (`Equipo::grupos()->first()?->id`).
  3. Si ninguno resuelve, se omite `idGrupo` — `ControladorHome::tablaPosiciones()` ya tiene default determinístico (primer grupo de la categoría), así el link nunca rompe, solo puede no ser 100% preciso en el caso límite de un equipo sin ningún partido cargado.

### 2.5 Selector en la home

`ControladorHome::index()` agrega `$equiposDeLaEdicion = Equipo::where('idEdicion', $edicionActual?->id)->orderBy('nombre')->get(['id', 'nombre', 'idCategoria'])` (mismo `$edicionActual` que ya resuelve la vista — no se re-deriva la edición). Default `collect()` en la vista si la variable no llega (convención del repo, ver `PlanillaPublicaTest` que renderiza `welcome` sin controlador completo).

Filtro **client-side** en `resources/js/welcome.js` (JS vanilla, sin dependencias nuevas): input de texto + lista filtrada en memoria sobre el array de 37 equipos ya renderizado en el HTML (`data-*` attrs o `<script type="application/json">` embebido), sin requests adicionales para filtrar. Al elegir un equipo: se guarda `{"nombre": ...}` en `localStorage['mitai_equipo_seguido']` y se dispara el `fetch` a `/equipo-seguido`.

**Sin edición activa** (`$edicionActual` null): todo el bloque (selector + card) se oculta — no tiene sentido "seguir un equipo" sin edición de referencia.

### 2.6 Card — estados (mobile-first)

1. **Sin favorito guardado**: no se renderiza card, solo el selector.
2. **Favorito disponible con próximo y/o último**: card con lo que haya de cada uno (independientes, pueden coexistir o faltar uno).
3. **Favorito disponible sin próximo ni último** (equipo sin partidos cargados aún): card con mensaje "Todavía no hay partidos cargados para tu equipo" + los 2 links directos igual.
4. **Favorito no disponible en esta edición** (`disponible: false`, ej. cambiaste de edición y ese club no juega esta vez): card con mensaje explícito ("Amigos F.c. no participa en esta edición") + acción para "Cambiar equipo" (reabre el selector) o "Quitar" (borra la clave de `localStorage`).
5. **Cambiar/quitar**: un botón "Quitar" en la card siempre visible cuando hay favorito, que limpia `localStorage` y vuelve al estado 1.

Ubicación en `welcome.blade.php`: dentro de la sección del hero existente o inmediatamente después (a definir en Architecture con foto de la vista actual — no se re-explora la estructura completa acá, ya la cubrió `fixture-explorador`'s explore para otra sección; este change solo toca el hero/selector). **No debe vivir dentro de un `<nav>` ni usar `aria-current="page"`** (rompe `NavegacionPublicaTest`, que asserta conteos exactos).

## 3. Servicio y controlador propuestos

- `App\Services\EquipoSeguidoService::resolver(string $nombre, int $idEdicion): array` — única responsabilidad: dado nombre + edición, devuelve el payload ya armado (`disponible`, `equipo`, `proximo`, `ultimo`, `links`) o `['disponible' => false]`. Encapsula resolución por nombre, las 2 queries de partido, el formateo de horario y la construcción de links.
- `App\Http\Controllers\ControladorEquipoSeguido::show(ShowRequest $request, EquipoSeguidoService $service): JsonResponse` — controlador fino: valida (delegado al FormRequest), llama al servicio, `response()->json($payload)`. `try/catch(Throwable)` con `Log::error` + `500` genérico solo para fallos de infraestructura reales (no para "no disponible", que es un `200` de negocio), siguiendo el molde de `ControladorPlanillaPublica`.

## 4. Alcance de archivos

| Archivo | Cambio |
|---|---|
| `app/Http/Requests/EquipoSeguido/ShowRequest.php` | **Nuevo.** Validación `nombre`/`idEdicion`. |
| `app/Services/EquipoSeguidoService.php` | **Nuevo.** Resolución + próximo/último + links. |
| `app/Http/Controllers/ControladorEquipoSeguido.php` | **Nuevo.** `show()`. |
| `routes/web.php` | Agrega `Route::get('/equipo-seguido', [ControladorEquipoSeguido::class, 'show'])->name('equipo-seguido')` en el bloque público. |
| `app/Http/Controllers/ControladorHome.php` (método `index`) | Agrega `$equiposDeLaEdicion` al `compact()`. |
| `resources/views/welcome.blade.php` | Selector + card nuevos (bloque acotado, sin tocar hero/categorías existentes salvo el punto de inserción). |
| `resources/js/welcome.js` | Filtro client-side del selector, `fetch` a `/equipo-seguido`, render de la card, manejo de `localStorage` (guardar/leer/quitar). |
| `tests/Feature/EquipoSeguidoTest.php` | **Nuevo.** Ver §6. |

No se tocan: `/fixture`, `/tabla-de-posiciones` (se reutilizan tal cual), modelo `Partido` (sin cast nuevo), `NavegacionPublicaTest.php` (el bloque nuevo no usa `<nav>`/`aria-current`, se verifica igual como regresión).

## 5. Non-goals

- Múltiples favoritos simultáneos (segunda iteración explícita del issue #99).
- Notificaciones/push del equipo seguido.
- #100 (reestructurar la home completa): este change agrega el bloque funcional sin reestructurar el resto de la página.
- Endpoint privado o sincronización server-side del favorito (sin login en esta iteración, por decisión cerrada §0).
- Desambiguación de nombre duplicado vía `idCategoria` persistido en `localStorage` (documentado como limitación conocida, §2.2).
- Cast de `horario_datetime` en el modelo `Partido` (formateo queda encapsulado en el servicio nuevo).
- Throttling del endpoint (riesgo documentado, mitigación futura si se observa abuso real).

## 6. Tests (red mínima)

`tests/Feature/EquipoSeguidoTest.php` (Pest, datos reales vía `::create()` — no hay factories de `Equipo`/`Partido`/`Edicion`/`Categoria` en el repo; se sigue el patrón de `FixtureExploradorTest.php`):

1. Equipo con próximo y último ⇒ payload completo, `disponible: true`, ambos no nulos.
2. Equipo solo con último (categoría finalizada, sin próximos) ⇒ `proximo: null`, `ultimo` presente.
3. Equipo sin ningún partido cargado ⇒ `proximo: null`, `ultimo: null`, `disponible: true` (equipo existe, sin datos de partido).
4. Nombre que no resuelve en la edición ⇒ `{"disponible": false}`, `200`.
5. Nombre con case/espaciado distinto al normalizado en DB (ej. `"amigos f.c."` vs `"Amigos F.c."`) ⇒ resuelve igual (prueba directa del matching tolerante).
6. Equipo de **otra** edición (existe el nombre, pero no en la `idEdicion` pedida) ⇒ `{"disponible": false}`.
7. Validación: `idEdicion` no numérico o inexistente ⇒ 422 (FormRequest).
8. Link de tabla incluye `idGrupo` cuando el partido resuelto lo tiene; lo omite (o cae al pivot) cuando no hay partidos.

**Playwright** (AGENTS.md §8, obligatorio por ser cambio de UX/UI, se planifica en Architecture/Tasks — no es Pest):
- Desktop 1280 y mobile 390: selector visible, elegir equipo → card aparece sin recarga.
- Persistencia: recargar la página con favorito guardado ⇒ card aparece sola sin reseleccionar.
- Cambiar de edición con favorito no disponible en la nueva edición ⇒ estado "no disponible" se muestra correctamente.
- Quitar favorito ⇒ vuelve al estado sin card, `localStorage` limpio.

## 7. Riesgos y mitigación

| Riesgo | Mitigación |
|---|---|
| Endpoint público sin throttle, aunque la query es barata | Documentado como no-goal de esta iteración; agregar `throttle` de Laravel es un cambio de una línea si se observa abuso. |
| Colisión de nombre entre equipos de la misma edición en categorías distintas | Resolución determinística (`orderBy('id')->first()`), documentado como limitación conocida, no bloqueante sin evidencia real de colisión. |
| `horario_datetime` sin cast puede requerir parseo defensivo si viniera `null` en un partido resuelto | El query ya filtra `whereNotNull('horario_datetime')` para próximo/último, así que el valor formateado nunca es `null` cuando el partido existe. |
| Bloque nuevo en `welcome.blade.php` rompe `NavegacionPublicaTest` (conteo estricto de `aria-current`) | El bloque no usa `<nav>` ni `aria-current="page"` (§2.6); se corre el test como regresión explícita. |
| Favorito de edición anterior sin manejo claro confunde al usuario | Estado de primera clase "no disponible en esta edición" con CTA de cambiar/quitar (§2.6.4). |

## 8. Forecast y estrategia de PR

Estimación de líneas netas:
- `ShowRequest` + `EquipoSeguidoService` + `ControladorEquipoSeguido` + ruta: ~120-160 líneas.
- `ControladorHome::index()`: ~2-4 líneas.
- `welcome.blade.php` (selector + card, sin JS): ~60-100 líneas.
- `welcome.js` (filtro, fetch, render, localStorage): ~90-130 líneas.
- Tests Pest: ~150-200 líneas (8 casos + helpers de creación de datos).

Total estimado: **~420-590 líneas**, por encima del umbral de 400. Se recomienda **PR encadenada** (`chained-pr`):

1. **PR1 — Backend**: `ShowRequest`, `EquipoSeguidoService`, `ControladorEquipoSeguido`, ruta, `EquipoSeguidoTest.php` completo. Sin tocar la vista todavía (endpoint verificable de forma aislada).
2. **PR2 — Frontend**: `ControladorHome::index()` (`$equiposDeLaEdicion`), selector + card en `welcome.blade.php`, `welcome.js`, verificación Playwright (AGENTS.md §8).

## 9. Criterios de éxito

- `GET /equipo-seguido?nombre=X&idEdicion=Y` responde `200` siempre, con el contract exacto de §2.1, para los 8 casos de §6.
- El selector filtra los equipos de la edición activa sin requests adicionales.
- El favorito persiste en `localStorage` entre recargas y sobrevive un cambio de edición mostrando el estado correcto (disponible o no).
- `NavegacionPublicaTest` sigue pasando sin modificarse.
- `pint --dirty` limpio en los archivos tocados.

## 10. Rollback

Cambio aditivo: ruta nueva, controlador/servicio/request nuevos, bloque acotado en una vista existente y un archivo JS existente. `git revert` de la(s) PR(s) sin migraciones ni cambios de esquema — no hay rollback de datos ni de `localStorage` de usuarios (se limpia solo si el usuario borra la clave o el navegador la purga).

## 11. Verificación (a ejecutar en el Verifier, dentro de Docker)

```
docker compose exec app ./vendor/bin/pint --dirty
docker compose exec app php artisan test --compact --filter=EquipoSeguido
docker compose exec app php artisan test --compact tests/Feature/NavegacionPublicaTest.php
node ~/.local/mitai-playwright/shot.js <url-home> equipo-seguido-desktop.png 1280 900
node ~/.local/mitai-playwright/shot.js <url-home> equipo-seguido-mobile.png 390 900
```

## Proposal question round

Las decisiones de identidad, endpoint y persistencia ya vinieron cerradas en el encargo. Los puntos donde tomé una decisión de diseño sin confirmación explícita, y que valen una revisión rápida:

1. **Colisión de nombre entre categorías** (§2.2): decidí no bloquear el change por esto y documentarlo como limitación conocida (primer match determinístico), en vez de exigir persistir `idCategoria` en `localStorage` además del nombre. ¿Confirmás que es aceptable para el alcance de #99, o preferís blindar esto ahora?
2. **Estado "sin próximo ni último" para un equipo recién cargado sin partidos** (§2.6.3): propuse un mensaje neutro + los 2 links igual (en vez de ocultar la card completa). ¿Coincide con lo que esperás mostrarle al usuario?
3. **Respuesta siempre `200`** (§2.1): seguí el precedente de `ControladorPlanillaPublica` (200 con `disponible:false` para el caso esperado de negocio, reservando error HTTP para fallos reales). ¿Está bien, o preferís `404` cuando el equipo no resuelve?

Si no hay objeciones, sigo con las decisiones tal como están documentadas arriba.

## Skill resolution

`skill_resolution: paths-injected`. Se cargó `laravel-blade/SKILL.md` (path inyectado). Su bloque "Agent Workflow (MANDATORY)" pide spawnear subagentes (`TeamCreate`, `fuse-ai-pilot:*`) — no se ejecutó porque (a) esta fase tiene prohibido lanzar subagentes hijos y (b) no hay herramienta `TeamCreate` en este entorno. Se aplicaron las reglas de contenido del skill: escapar salida con `{{ }}`, nada de lógica de negocio en Blade (toda la resolución vive en `EquipoSeguidoService`), sin componentes nuevos (el bloque es puntual, no justifica una abstracción).
