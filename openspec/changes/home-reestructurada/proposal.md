# Propuesta — `home-reestructurada` (issue #100)

## Rama de implementación

`feat/home-reestructurada-vista`, **apilada sobre `feat/seguir-equipo-vista`**
(que ya contiene el bloque `#seguir-equipo-bloque` + `welcome.js` de #99). Orden
de merge: `#119` (seguir-equipo) → `#120` → esta. Este change **reubica** el
bloque de #99 dentro de la nueva jerarquía; no lo duplica ni lo reescribe.

## Intención

Reestructurar `welcome.blade.php` para que la primera pantalla (mobile, 390px)
muestre información viva del torneo — fecha relevante con sus partidos — en
vez de prosa institucional y CTAs redundantes. Eliminar ~290 palabras de texto
decorativo, deduplicar 3 accesos a `fixture` y 2 pares de rutas casi
homónimas (`goleadores`/`tabla-goleadores`, `vallas`/`tabla-vallas`), y
eliminar código muerto del controlador (`$roles`, `$idCategoria`).

## Jerarquía final de la home (mobile-first, orden real del documento)

1. Alertas de sesión (sin cambios).
2. **Hero mínimo**: nombre de la edición actual (`<h1>`) + `<form>`/`<select>`
   de edición (conservado, compacto) + 1 CTA primario ("Ver partidos" →
   ancla al bloque 3) + 1 CTA secundario ("Seguir mi equipo" → ancla al
   bloque de #99).
3. **Bloque de información viva** (`id="fecha-relevante"`, nuevo): etiqueta
   dinámica "Próximos partidos" / "Fecha pendiente" + nombre de la fecha +
   listado completo de sus partidos (tarjetas `<x-tarjeta-partido>`) + link
   "Ver todos los partidos" → `route('fixture', ['idEdicion' => ...])`.
4. **Bloque "Seguir mi equipo"** (`#seguir-equipo-bloque`, reubicado tal cual
   de `feat/seguir-equipo-vista`, mismos ids y contrato con `welcome.js`).
5. **Accesos secundarios** (nuevo grupo de 4 chips): Posiciones ·
   Goleadores · Finales · Vallas → `tabla-posiciones`, `tabla-goleadores`,
   `instancias-finales`, `tabla-vallas`.
6. Carrusel de categorías (conservado, kicker/párrafo eliminados).
7. **Historial** (reducido a links): 4 tarjetas sin prosa descriptiva ni
   kickers — solo icono + título + "Ver más" → `campeones`, `subcampeones`,
   `goleadores` (histórico), `vallas` (histórico).
8. Comunidad (3 links de redes conservados, párrafo y kicker eliminados) +
   institucional recortado a los 4 bullets (sin `<p>` de 45 palabras) — o
   eliminado si el dueño lo confirma en la fase de tareas; queda como
   candidato adicional, no forma parte del pedido original y no se toca sin
   confirmación explícita.
9. Footer (sin cambios).

## Contrato del bloque de información viva

- **Controlador** (`ControladorHome::index`): inyecta `FixtureExploradorService`
  y agrega:
  ```php
  $idFechaRelevante = $edicionActual
      ? $this->fixtureExplorador->resolverFechaRelevante($edicionActual->id)
      : null;
  $partidosFechaRelevante = $idFechaRelevante
      ? Partido::select(...)->join(...)->where('idFechas', $idFechaRelevante)->get()
      : collect();
  $fechaRelevante = $idFechaRelevante ? Fecha::find($idFechaRelevante) : null;
  $hayPartidosFuturos = $partidosFechaRelevante->contains(fn ($p) => !$p->jugado
      && $p->horario_datetime && \Carbon\Carbon::parse($p->horario_datetime)->isFuture());
  ```
  Reutiliza **exactamente** `resolverFechaRelevante()` de
  `FixtureExploradorService` (ya usado por `fixture()`), sin duplicar el
  predicado `jugado=0 OR NULL` + `horario >= now()`.
- **Etiqueta dinámica**: `$hayPartidosFuturos ? 'Próximos partidos' : 'Fecha pendiente'`.
  No hay estado "torneo finalizado" separado: si la fecha relevante resuelta
  es la última jugada o la primera por id (fallback de
  `resolverFechaRelevante`), la etiqueta sigue siendo "Fecha pendiente" y el
  bloque muestra igual el listado (transparencia de estado, no vacío).
- **Alcance**: la fecha completa (todos sus partidos, sin límite artificial —
  puede ser 1 o 30 según la edición), igual que hace `fixture()` por fecha.
  Sin filtro por categoría ni por equipo favorito: el favorito se resuelve en
  el bloque 4, separado.
- **Vista**: nueva sección en `welcome.blade.php` que reutiliza
  `<x-tarjeta-partido>` (ver componente nuevo abajo) en un `foreach`.

## Componente nuevo: `<x-tarjeta-partido>`

- Ruta: `resources/views/components/tarjeta-partido.blade.php` (anónimo — el
  repo ya tiene 18 componentes anónimos en `resources/views/components/` y
  ningún precedente de clase-componente para este tipo de UI; se sigue esa
  convención, no la de `partials/`).
- `@props(['partido', 'interzonal' => false])`.
- Markup: extracción mecánica y literal de las ~110 líneas de
  `layouts/fixture.blade.php:236-345` (variante estándar, gradiente teal) y
  `layouts/fixture.blade.php:366-449` (variante interzonal, gradiente ámbar +
  badge "Interzonal"), unificadas con la clase condicionada por `$interzonal`.
  Mismos data-attributes (`data-jugado`, `data-horario`, `data-local`,
  `data-visitante`, `id="partido-{{ $p->id }}"`) porque el JS de filtros de
  fixture (`fixture.js` o inline) puede depender de ellos — **verificar en
  task planning** si hay JS que selecciona `.partido-card` en la home; si lo
  hay, decidir si se reutiliza o se excluye con un flag adicional.
- `fixture.blade.php` reemplaza sus 2 copias inline por
  `<x-tarjeta-partido :partido="$p" />` y
  `<x-tarjeta-partido :partido="$p" :interzonal="true" />`. Reemplazo
  mecánico: verificar con Playwright que el fixture renderiza pixel-a-pixel
  igual (mismo HTML generado) antes de dar por cerrada la extracción.
- Fallback autorizado si aparece una variante no prevista al implementar
  (ej. botón de planilla con estado distinto): duplicar el markup puntual en
  la home y documentarlo como deuda en el PR, sin bloquear la entrega.

## Hero — texto que se elimina

| Elemento | Contenido actual |
| --- | --- |
| Kicker pill | "fútbol formativo" |
| `<h1>` | "Viví la pasión de Mita'í en cada categoría" → se reemplaza por el nombre de la edición actual |
| `<p>` (33 palabras) | "Descubrí fixtures actualizados, instancias decisivas y a los protagonistas de la {edición}. Elegí tu categoría favorita y seguí el torneo desde cualquier dispositivo." |
| 3 badges sin link | "Actualización en vivo" · "Comunidad Mita'í" · "Formación y valores" |
| CTA "Instancias Finales" | baja a accesos secundarios (bloque 5) |
| CTA "Goleadores al día" (`tabla-goleadores`) | se conserva como **el único** acceso a goleadores vivos, en accesos secundarios (bloque 5); se elimina de aquí |
| CTA "Vallas al día" (`tabla-vallas`) | se conserva como acceso secundario (bloque 5); se elimina de aquí |
| Card "Organizá tu experiencia": kicker + `<h2>` + `<p>` explicativo + 2 mini-cards | ~40 palabras + decoración; se conserva solo `<form>`+`<select>` |
| §4 kicker + `<p>` "Ingresá en segundos..." | ~25 palabras |
| §4 link "Ver todo el fixture" | **eliminado** (duplica el CTA de bloque 3) |
| §5 Historial: kicker + `<h2>` "Historial" + `<p>` + 4 kickers ("Historial"/"Podio"/"Defensa"/"Ataque") + 4 descripciones | ~65 palabras |
| §6 Comunidad: kicker + `<p>` (30 palabras) | eliminado (se conservan los 3 links) |
| §6 Institucional: kicker + `<h3>` + `<p>` (45 palabras) | eliminado; los 4 `<li>` quedan como candidato a conservar sin envoltorio (ver punto 8 de la jerarquía) |

Total ≈ 290 palabras + 1 kicker de hero + 3 badges + 2 CTAs duplicados del
hero + 1 link duplicado de §4.

## Deduplicación de enlaces (decisión documentada)

| Grupo | Antes | Después |
| --- | --- | --- |
| `fixture` | Hero CTA + link "Ver todo el fixture" + nav | 1 vez: bloque 3 ("Ver todos los partidos") + nav |
| Goleadores | Hero CTA `tabla-goleadores` + tarjeta histórica `goleadores` | `tabla-goleadores` en accesos secundarios (bloque 5, label "Goleadores"); `goleadores` se conserva **solo** dentro de Historial (bloque 7, ranking histórico, contexto distinto — no es duplicado real, es histórico vs. vivo) |
| Vallas | Hero CTA `tabla-vallas` + tarjeta histórica `vallas` | `tabla-vallas` en accesos secundarios (bloque 5, 4to chip, label "Vallas"); `vallas` se conserva dentro de Historial (mismo criterio que Goleadores) |
| Finales | Hero CTA `instancias-finales` | Baja a accesos secundarios (bloque 5) |
| Posiciones | Sin acceso en el cuerpo (solo nav) | Se **crea** en accesos secundarios (bloque 5) → `tabla-posiciones` |

## Controlador — qué se agrega y qué muere

**Se agrega:**
- Constructor con `FixtureExploradorService $fixtureExplorador` inyectado
  (verificar que no rompe la llamada actual a `fixture()`, que hoy no lo
  recibe por constructor — revisar si ya está inyectado ahí o si hay que
  unificar el punto de inyección).
- `$idFechaRelevante`, `$partidosFechaRelevante`, `$fechaRelevante`,
  `$hayPartidosFuturos` (ver contrato arriba).
- Ediciones/equipos de la edición para hidratar el bloque de #99 si ese
  contrato aún no está resuelto en la branch base (verificar contra
  `feat/seguir-equipo-vista` al momento de implementar; si ya lo provee, no
  duplicar).

**Muere:**
- `$roles = User::pluck('rol')` (query de todos los usuarios en cada request,
  0 usos en la vista).
- `$idCategoria` pasado a la vista (0 usos, ya no se necesita para el carrusel
  de categorías que no lo consume).
- El import de `User` si queda sin otros usos en el archivo.

## Alcance de archivos (estimado)

| Archivo | Tipo de cambio |
| --- | --- |
| `resources/views/welcome.blade.php` | Reescritura de hero + nueva sección de fecha relevante + accesos secundarios + recorte de historial/comunidad/institucional |
| `app/Http/Controllers/ControladorHome.php` | Agrega inyección de servicio + variables nuevas en `index()`; elimina `$roles`/`$idCategoria` |
| `resources/views/components/tarjeta-partido.blade.php` | Nuevo (extracción de fixture.blade.php) |
| `resources/views/layouts/fixture.blade.php` | Reemplazo mecánico de 2 bloques inline por `<x-tarjeta-partido>` |
| `tests/Feature/*Home*.php` (Pest) | Nuevo/actualizado |
| `tests/Feature/*Fixture*.php` (Pest) | Verificar que sigue en verde tras la extracción del componente |

No se tocan: `welcome.js` de #99 (se reubica el HTML, no el JS), rutas,
migraciones, modelos.

## Tests (Pest)

1. `it('muestra "Próximos partidos" cuando la fecha relevante tiene partidos futuros')`.
2. `it('muestra "Fecha pendiente" cuando la fecha relevante es histórica o sin horario')`.
3. `it('lista todos los partidos de la fecha relevante, no un subconjunto')`.
4. `it('no rompe cuando la edición no tiene partidos')` (colección vacía, sin error).
5. `tests/Feature/*Fixture*.php` existentes deben seguir pasando tras la
   extracción del componente (regresión, no test nuevo).

## Verificación visual (Playwright — AGENTS.md §8)

- Home en 390px y 1280px: primera pantalla mobile = información viva visible
  sin scroll (o con scroll mínimo), no hero decorativo.
- Fixture en 390px y 1280px, antes/después de la extracción del componente:
  captura comparativa para confirmar cero regresión visual.
- Comportamiento de nav sticky (`.nav-compacta`, `#nav-centinela` de #97) con
  el hero de nueva altura.

## Forecast

- Explore estimaba ~250-380 líneas para #100 solo. Con la extracción del
  componente compartido (que **resta** líneas netas de `fixture.blade.php` al
  mover ~220 líneas duplicadas a un archivo único usado 3 veces), el balance
  neto del change es negativo en líneas totales del repo aun sumando el
  bloque nuevo de home + controlador + tests.
- Verificar el número exacto de líneas añadidas/eliminadas en la fase de
  implementación antes de decidir si se entrega en una sola PR o se separa
  (backend/query primero, vista después) — el propio explore ya señala que
  roza el límite de 400 líneas reviewable.

## Riesgos

- `pint --test` sobre `ControladorHome.php` ya falla en `main` por deuda
  preexistente de imports; no reportar como regresión de este change, pero
  no ampliar la deuda (usar `--dirty`).
- Si `feat/seguir-equipo-vista` cambia su contrato de ids/selectors antes de
  que esta rama la reciba, hay que reconciliar el punto de inserción del
  bloque 4.
- Extracción del componente toca `fixture.blade.php` (#98 ya mergeado) —
  riesgo de regresión visual mitigado con Playwright antes/después.
- Datos verificados: edición 10 tiene 0 partidos futuros y 32 pendientes
  históricos de 137 → el camino "Fecha pendiente" **es** el camino real hoy,
  no un edge case teórico; probarlo explícitamente, no solo el camino feliz.

## Non-goals confirmados

- #98, #99 (ya resuelto/reubicado, no reescrito), #102-#104: fuera de
  alcance.
- Rediseño de identidad visual: mismos colores, mismo fondo, misma gramática
  de chips/cards existente.
- Contenido nuevo: no se inventan secciones ni copy adicional; solo se
  recorta y reordena lo existente.
- Bloque de alertas de sesión (79 líneas, candidato a `<x-alerta>`): fuera de
  alcance de #100, no se toca.

## Decisión de entrega

Pendiente de confirmar en task planning con el conteo real de líneas: dado
que el change resta más líneas de las que agrega (extracción de componente +
recorte de prosa), se espera **una sola PR** viable dentro del umbral de 400
líneas netas. Si el conteo real lo supera, split propuesto: (1) controlador +
servicio + componente + fixture.blade.php (mecánico, bajo riesgo visual),
(2) vista de home (jerarquía nueva, mayor superficie de revisión).
