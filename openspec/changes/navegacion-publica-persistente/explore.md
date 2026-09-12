# Exploración — navegacion-publica-persistente (issue #97)

Solo exploración. No se implementó nada.

Nota: el explore paraguas `openspec/changes/ux-portal-publico/explore.md` **no existe en disco** (solo hay
`autosave-planilla-por-jugador` y `copiar-planilla-partido-anterior`). Esta exploración se hizo desde cero
sobre la superficie de #97.

## 1. Anatomía de `resources/views/layouts/navigation.blade.php`

Archivo único, ~95 líneas, sin componentes propios salvo Breeze. Estructura:

- `<nav class="relative z-50 border-b border-white/10 bg-black/40 backdrop-blur">` — no es sticky hoy.
- Contenedor: `mx-auto flex max-w-6xl flex-col items-center gap-4 px-4 py-4 sm:flex-row sm:justify-between sm:px-6 lg:px-8`.
- `@php $edicionActiva = $EdicionSeleccionada ?? $ultimaEdicion ?? null; @endphp` (línea 5) — único punto de
  resolución de contexto de edición.
- `$homeUrl = optional($edicionActiva)->id ? route('home', ['idEdicion' => ...]) : route('home')`.
- Bloque logo: `<a href="{{ $homeUrl }}">` con imagen + "Torneo Mitai" + `optional($edicionActiva)->nombre ?? 'Edición vigente'`.
  **Ese texto es hoy el único indicador de edición activa.** No hay selector de edición en la nav pública
  (el selector vive en `Panel/admin.blade.php`).
- Bloque derecho: `flex w-full flex-wrap items-center justify-center gap-3 sm:ml-auto sm:w-auto sm:justify-end`.
  - `@auth` + `Auth::user()->rol === 'admin'` → link "Panel de administración" a `route('admin', ['idEdicion' => ...])`.
  - `@auth` → `<x-dropdown align="right" width="56">` (Breeze, Alpine) con avatar, `x-dropdown-link` a
    `profile.edit` (con o sin `idEdicion`), y form POST de logout.
  - `@else` → botón "Iniciar sesión" a `route('login')`.

**Componentes Blade usados:** `x-dropdown`, `x-dropdown-link` (Breeze). Hay un `<div x-data="{ open: false }">`
envolviendo el dropdown que es redundante (el propio `x-dropdown` maneja su estado) — ruido preexistente.

**Breakpoints:** solo `sm:` y `lg:` (padding). No hay `md:`. No hay hamburguesa ni bottom nav.

**Cómo llega `$EdicionSeleccionada`:** NO hay view composer para la nav pública. El composer de
`app/Providers/AppServiceProvider.php:75` está registrado **solo para `Panel.admin`**. En público, cada
método de `ControladorHome` hace `compact('EdicionSeleccionada', ...)` a mano.

## 2. Matriz vista → origen de la edición

| Vista (layout) | Controlador | Origen de `idEdicion` | `$ultimaEdicion` disponible | Fallback si no hay param |
|---|---|---|---|---|
| `welcome` (home) | `ControladorHome::index` | `$request->query('idEdicion')` | Sí (`Edicion::latest('id')` solo si el query es null) | nav muestra última edición |
| `layouts.fixture` | `fixture` | `$request->idEdicion` | **No** | nav muestra "Edición vigente" |
| `layouts.tabla-posiciones` | `tablaPosiciones` | `$request->query('idEdicion')` | **No** | idem |
| `layouts.tabla-goleadores` | `tablaGoleadores` | **sesión** (`tabla_goleadores_filtros`) vía PRG | **No** | idem |
| `layouts.tabla-vallas` | `tablaVallas` | `$request->query('idEdicion')` | **No** | idem |
| `layouts.instancias-finales` | `instanciasFinales` | `$request->query('idEdicion', $ultimaEdicion->id)` | Sí | última edición |

Además, con nav incluida: `layouts.goleadores`, `layouts.vallas`, `layouts.campeones`, `layouts.subcampeones`,
`layouts.app`.

**Conclusión operativa:** el idiom del proyecto para propagar edición es **query param `idEdicion`**, no sesión.
La única excepción es `tabla-goleadores`, que usa PRG: si le mandás `?idEdicion=N` lo guarda en sesión y hace
`redirect()->route('tabla-goleadores')` (302 extra, URL limpia). El link con query param **sí funciona** ahí.

Riesgo real detectado: en 4 de 6 vistas `$ultimaEdicion` **no está definida**, así que `?? $ultimaEdicion ?? null`
cae a `null` y la nav muestra "Edición vigente" en vez del nombre. Si la nav va a construir links con
`idEdicion`, navegar desde una vista sin param **pierde el contexto**. Esto hay que resolverlo en la propuesta
(view composer público o resolución de última edición en la propia nav).

## 3. Rutas públicas del menú (`routes/web.php:52-63`)

| Ítem menú | Nombre de ruta | URI | Params obligatorios | Notas |
|---|---|---|---|---|
| Inicio | `home` | `/` | ninguno | `idEdicion` opcional |
| Partidos | `fixture` | `/fixture` | ninguno | acepta `idEdicion`, `idCategoria`, `idFecha`, `estado`, `orden` |
| Posiciones | `tabla-posiciones` | `/tabla-de-posiciones` | ninguno técnicamente | sin `idCategoria` muestra "Categoría no encontrada" → **UX degradada** |
| Goleadores | `tabla-goleadores` | `/tabla-de-goleadores` | ninguno | PRG: query params → sesión + 302 |
| Finales | `instancias-finales` | `/instancias-finales` | ninguno | default a última edición |

Fuera del menú de #97 pero existentes: `tabla-vallas` (`/tabla-de-vallas`), `campeones`, `subcampeones`,
`goleadores`, `vallas`.

## 4. Patrón de sección activa recomendado

El proyecto **ya usa** `request()->routeIs(...)` — la referencia canónica es `Panel/admin.blade.php:512-543`:
un array `['patterns' => [...], 'modulo' => '...']` recorrido con `routeIs()` y `break 2`. También hay
`Route::is('admin')` y `request()->is('Panel*')` en `head.blade.php:35`.

Recomendación: replicar ese idiom, pero **mucho más chico**, con un array plano de items
`['label', 'ruta', 'patterns']` y `request()->routeIs($patterns)` (que acepta varargs/array), calculando
`$esActivo` por item. Es el patrón que un revisor de este repo va a reconocer. Marcar con
`aria-current="page"` además del estilo visual — hoy eso no existe en ningún lado del proyecto.

## 5. Plan mobile

Situación actual: **no hay hamburguesa ni bottom nav en ningún lado del repo**. Alpine solo se usa vía
`x-dropdown` de Breeze y en `x-data` locales de las vistas.

El layout actual ya apila en mobile (`flex-col` → `sm:flex-row`) y usa `flex-wrap`, así que hoy no hay overflow
horizontal; agregar 5 links en la misma fila **sí lo introduciría**.

Opciones para la propuesta (decisión de arquitectura, no la tomo acá):
- **A. Fila scrollable horizontal** (`flex overflow-x-auto snap-x` + `scrollbar-none`): mínimo cambio, un solo
  markup para desktop y mobile, sin JS. Contra: descubribilidad menor.
- **B. Bottom navigation fija** (`fixed bottom-0 inset-x-0 z-50 md:hidden` + `pb-*` en el `<main>` para no tapar
  contenido): cumple mejor el issue, pero toca los 11 layouts para el padding inferior o requiere ponerlo en la
  nav con un `body`-level spacer. Contra: hay layouts con `overflow-x-hidden` y fondos absolutos
  (`layouts/fixture.blade.php:4-10`) donde un `fixed` interactúa con el `isolate`/`z-index`.
- **C. Hamburguesa Alpine** (`x-data="{ open: false }"` + `x-show`): consistente con Breeze, pero agrega 2 taps
  para llegar a Partidos, lo que **contradice el criterio "un único paso"**.

Nota Tailwind: el proyecto es **v3**. La skill de layouts avanzados que me pasaron documenta sintaxis v4
(`@utility`, `@container`, container queries). **No aplica acá** — nada de `@utility` ni `@container`;
`scrollbar-none` habría que definirlo a mano o usar `[&::-webkit-scrollbar]:hidden`.

## 6. Accesibilidad

- Focus states hoy: solo el botón de login y el trigger del dropdown tienen
  `focus:outline-none focus:ring-2 focus:ring-[#f7f280]/40`. El link del logo y el link de "Panel de
  administración" **no tienen focus ring** (solo `hover:`).
- `x-dropdown-link` y el botón de logout sí tienen `focus:bg-*`.
- No hay `aria-current`, ni `aria-label` en el `<nav>`, ni skip link en ningún layout.

Mínimo para cumplir sin rediseñar:
1. Reusar el token de focus existente (`focus-visible:ring-2 focus-visible:ring-[#f7f280]/40`) en los 5 links nuevos.
2. `aria-current="page"` en el activo.
3. `aria-label="Navegación principal"` en el `<nav>`.
4. Contraste del estado activo: hoy el gris es `text-white/60`–`/70`; el activo debería ir a `text-white` +
   borde/underline, porque **el color solo no alcanza** si el inactivo ya es blanco translúcido.

## 7. Riesgos de alcance

`layouts.navigation` está incluido por **11 archivos**:

Públicos: `welcome.blade.php:6`, `layouts/fixture:12`, `layouts/tabla-posiciones:12`,
`layouts/tabla-goleadores:12`, `layouts/tabla-vallas:19`, `layouts/instancias-finales:12`,
`layouts/goleadores:12`, `layouts/vallas:12`, `layouts/campeones:12`, `layouts/subcampeones:12`.

Autenticado: `layouts/app.blade.php:4` → consumido por `<x-app-layout>` en `dashboard.blade.php:1` y
`profile/edit.blade.php:1`. Es decir, **la nav pública también es la nav del perfil y del dashboard**.

El **panel admin NO usa este partial**: `Panel/admin.blade.php` tiene su propio header con selector de edición
y contexto. Cambiar `navigation.blade.php` no rompe el panel.

Riesgos concretos:
- Tocar la nav impacta 12 páginas renderizadas. No hay ningún test que ejerza estas vistas públicas
  (`tests/Feature/` solo cubre Auth, Panel, PlanillaPublica, Profile). **Red de seguridad = cero.**
- `layouts/tabla-vallas.blade.php` incluye la nav en la línea 19, no 12 — su cabecera es distinta; verificar
  que no haya markup duplicado alrededor.
- Un bottom nav `fixed` puede tapar contenido en los 10 layouts públicos, cada uno con su propio `<main>`.
- Cualquier link que agregue `idEdicion` a `tabla-goleadores` dispara un 302 extra (PRG). Funciona, pero hay
  que saberlo antes de escribir asserts en tests.

## Preguntas abiertas para la propuesta

1. **¿"Partidos" apunta a `fixture`?** El issue dice "Partidos" pero la ruta se llama `fixture`. Confirmar que
   es la misma sección y si se renombra el label o la ruta.
2. **¿Entra "Vallas" al menú?** Existe `tabla-vallas` como sección pública equivalente a goleadores y el issue
   no la lista. ¿Se omite a propósito o es un olvido?
3. **¿Cómo se resuelve la edición cuando la vista no la trae?** Tres caminos: (a) view composer público que
   comparta `edicionActiva` en todos los layouts, (b) resolver `Edicion::latest()` dentro de la nav (query en
   cada request de cada página), (c) tocar los 6 controladores. (a) es lo más limpio y es el patrón que ya
   existe para el panel.
4. **¿Posiciones sin `idCategoria`?** Hoy renderiza "Categoría no encontrada". ¿El link del menú debe llevar
   una categoría por defecto, o se arregla la vista para que sin categoría muestre un selector?
5. **Mobile: ¿A, B o C?** (ver sección 5). El criterio "un único paso" descarta C.
6. **¿Se agrega `sticky top-0` a la nav?** Hoy no lo es. Persistente ≠ sticky, pero conviene definirlo.

## Verificación pendiente para la fase de implementación

- No se corrió ningún test (no hay cobertura de vistas públicas que valga la pena filtrar hoy).
- El change va a necesitar tests Pest nuevos tipo `tests/Feature/NavegacionPublicaTest.php` que hagan
  `get(route('fixture', ['idEdicion' => $e->id]))->assertSee(route('tabla-posiciones', [...]))`.
- Gates del proyecto: `docker compose exec app ./vendor/bin/pint --test` y `docker compose exec app php artisan test`.
