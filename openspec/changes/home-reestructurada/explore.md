# Explore — `home-reestructurada` (issue #100)

Fase: exploración. No se implementó nada. Solo se escribió este archivo.

## 0. Limitación de esta fase (leer primero)

**No hubo shell disponible en esta fase**: las herramientas expuestas fueron
`read`, `grep`, `find`, `write`, `edit`, `codegraph`, `mem_save`. **No se pudo
ejecutar `docker compose exec app php artisan tinker`.** Todos los datos de este
documento son estáticos (lectura de código). Los conteos de partidos futuros
quedan como **verificación pendiente para la fase de requirements/design**
(comandos propuestos en §6).

## 1. HALLAZGO BLOQUEANTE — el bloque "Seguir mi equipo" de #99 **NO EXISTE** en esta branch

El encargo asumía que #99 ya estaba mergeado ("selector + card en la home vía
`welcome.js` y `localStorage`", endpoint `/equipo-seguido`). **Es falso en
`feat/home-reestructurada` (base `c8beebe`).** Evidencia:

| Verificación | Resultado |
| --- | --- |
| `grep -r "seguir-equipo\|equipo-seguido\|equipoSeguido\|seguirEquipo"` (excluyendo `openspec/` y `.git/`) | **0 matches** |
| `routes/web.php` grep `equipo` | solo rutas de panel admin (`equipo.*`, `equipoedicion.*`, `buscar-equipos`). No hay `/equipo-seguido` |
| `resources/js/welcome.js` (43 líneas, leído completo) | solo dropdowns de nav (`navbar-dropdown`, `dropdownEdiciones`, `dropdownUser`) + `descargarPDF()` muerto. Cero `localStorage`, cero fetch |
| `welcome.blade.php` | sin ningún contenedor `#seguir-equipo-bloque` ni equivalente |

**Consecuencia para el plan:** #100 no puede "reubicar" el favorito — no hay nada
que reubicar. Hay dos caminos y esto es una **decisión del dueño, no mía** (ver
§9, pregunta abierta 1): (a) #100 deja un hueco declarado (slot vacío + orden
definido) y #99 lo llena después; (b) se fusiona el alcance. En el explore
paraguas (`openspec/changes/ux-portal-publico/explore.md:181-182`) #100 y #99 son
unidades 8 y 9, **separadas y secuenciales**, lo que favorece (a).

## 2. Inventario de `resources/views/welcome.blade.php` EN ORDEN

Archivo monolítico, ~470 líneas, sin layout ni componentes: `@include('head')` +
`@include('layouts.navigation')` + `<main>` con todo inline. Cero partials
reutilizables. Ningún `<x-...>`.

| # | Sección | Líneas aprox | Qué es | Veredicto #100 |
| --- | --- | --- | --- | --- |
| 0 | `head` + `layouts.navigation` | 1-6 | Nav sticky rol-aware (ya trabajada en #97) | **Conservar intacto** |
| 1 | Bloque de alertas de sesión | 8-86 (~79 líneas) | 5 variantes casi idénticas (`verified`, `warning`, `success/status`, `error`, `info`), cada una con icono + `<h3>` + `<p>` + botón cerrar `onclick` inline | **Fuera de alcance funcional pero es el 17% del archivo.** Candidato natural a `<x-alerta type="">`. Solo si el dueño lo autoriza (no lo pide #100) |
| 2 | `@php $edicionActual = $EdicionSeleccionada ?? $ultimaEdicion` | 88-90 | Resolución de edición | Conservar (lógica que idealmente sube al controlador) |
| 3 | **HERO** (2 columnas, `py-16 sm:py-24`) | 92-222 (~131 líneas) | Ver desglose abajo | **Reescritura completa** |
| 3a | Fondo: `fondo_en_cancha.webp` + 2 blobs blur decorativos | 93-99 | Decoración | Conservar imagen; blobs opcionales |
| 3b | Kicker pill "fútbol formativo" | 103-107 | Texto decorativo | **ELIMINAR** |
| 3c | `<h1>` "Viví la pasión de Mita'í en cada categoría" | 108-110 | Título marketinero | **REEMPLAZAR** por el nombre de la edición (paralelo al hero de Fixture recortado a "Fixture") |
| 3d | `<p>` "Descubrí fixtures actualizados, instancias decisivas y a los protagonistas de la {edición}. Elegí tu categoría favorita y seguí el torneo desde cualquier dispositivo." | 111-115 | ~33 palabras de relleno | **ELIMINAR** |
| 3e | 3 badges: "Actualización en vivo", "Comunidad Mita'í", "Formación y valores" | 116-129 | Decoración pura, sin link | **ELIMINAR** |
| 3f | **4 CTAs** en el hero: Fase de Grupos (`fixture`), Instancias Finales, Goleadores al día, Vallas al día | 130-153 | Cuatro botones compitiendo entre sí | **REDUCIR a 1 primario** (próximos partidos / `fixture`) **+ 1 secundario** (seguir equipo). Finales/Goleadores/Vallas bajan a accesos secundarios |
| 3g | **Card lateral "Organizá tu experiencia"** con: icono, kicker "selecciona tu edición", `<h2>` "Organizá tu experiencia", **`<form GET>` con `<select name="idEdicion" onchange="this.form.submit()">`**, `<p>` explicativo, y 2 mini-cards decorativas ("Tablas actualizadas", "Comparte emociones") | 154-222 (~69 líneas) | El `<select>` es **funcionalmente obligatorio** (criterio: conservar selector). El resto es envoltorio | **CONSERVAR solo el `<form>`+`<select>`.** ELIMINAR: kicker, `<h2>` "Organizá tu experiencia", `<p>` explicativo, las 2 mini-cards. En mobile ocupa una pantalla entera |
| 4 | Sección **"Accesos directos"** (categorías) | 223-268 (~46 líneas) | Kicker "accesos rápidos" + `<h2>` "Accesos directos" + `<p>` "Ingresá en segundos a los apartados más consultados..." + link "Ver todo el fixture" + **carrusel horizontal de categorías** → `route('fixture', [idEdicion, idCategoria])` | **CONSERVAR el carrusel** (criterio: mantener acceso a categorías). ELIMINAR kicker + `<p>`. **Título duplicado de intención con el hero.** El link "Ver todo el fixture" **duplica el CTA "Fase de Grupos"** del hero |
| 5 | Sección **"Historial"** (4 tarjetas grandes) | 272-385 (~114 líneas) | Kicker "legado del torneo" + `<h2>` "Historial" + `<p>` "Recorré a los protagonistas que hicieron historia..." + 4 tarjetas full-color (Campeones, Subcampeones, Vallas menos vencidas, Goleadores), cada una con kicker + `<h3>` + descripción + "Ver más" | **BAJAR jerarquía.** Ojo: **Goleadores y Vallas acá duplican los CTAs 3f del hero** (distinta ruta: `goleadores`/`vallas` histórico vs `tabla-goleadores`/`tabla-vallas` vivo — confusión real de producto). En mobile son 4 cards de ~260px en carrusel snap |
| 6 | Sección **Comunidad + Institucional** | 389-467 (~79 líneas) | Col izq: kicker "comunidad" + `<h2>` "Sumate a la comunidad Mita'í" + `<p>` (~30 palabras) + 3 botones (Instagram, Facebook, mail). Col der: logo + kicker "Sobre el torneo" + `<h3>` "Formando talentos con pasión" + `<p>` (~45 palabras) + `<ul>` de 4 bullets institucionales | **MOVER AL FONDO / RECORTAR FUERTE.** Los 4 bullets y el `<p>` "Mita'í reúne a cientos de chicos..." son el texto institucional más puro del archivo. Las redes sociales tienen valor (conservar los 3 links, tirar el envoltorio) |
| 7 | Footer | 469-487 | Copyright + crédito del desarrollador + LinkedIn | Conservar |

### Accesos repetidos (inventario explícito)

| Destino | Aparece en |
| --- | --- |
| `fixture` (edición) | Hero CTA "Fase de Grupos" (3f) + "Ver todo el fixture" (§4) + nav "Partidos" → **3 veces** |
| `tabla-goleadores` | Hero CTA "Goleadores al día" (3f) + nav "Goleadores" |
| `goleadores` (histórico) | Tarjeta §5 — nombre casi idéntico al anterior, destino distinto |
| `tabla-vallas` / `vallas` | Mismo patrón duplicado hero vs §5 |
| `instancias-finales` | Hero CTA (3f) + nav "Finales" |
| categorías → fixture | Carrusel §4 (único acceso, OK) |

`tabla-posiciones` **no tiene ningún acceso en el cuerpo de la home** (solo en el
nav). El issue pide agruparlo con Goleadores/Finales como acceso secundario: hay
que **crearlo**, no reubicarlo.

## 3. Jerarquía mobile actual (390px) — primera pantalla

Sin capturas de Playwright en esta fase (sin shell). Estimación estática con las
clases Tailwind: nav sticky (~72px) → hero `py-16` (64px) → kicker pill (~30px) →
`<h1>` `text-3xl` a 3-4 líneas (~130px) → `<p>` a 4-5 líneas (~110px) → 3 badges
en wrap (~70px) → 4 CTAs en `flex-wrap` (~2-3 filas, ~130px).

**Lo que ve el usuario en la primera pantalla de un teléfono (~844px de alto):
nav, kicker decorativo, título marketinero, párrafo, badges y, con suerte, el
primer CTA.** Cero información viva. El selector de edición (columna lateral
`lg:w-5/12`, que en mobile va **debajo**) aparece recién después de ~600px más de
scroll. Próximos partidos: no existen. Favorito: no existe.

**Verificación Playwright obligatoria** (AGENTS.md §8) en 390px y 1280px, antes y
después — pendiente para las fases con shell.

## 4. `ControladorHome::index()` — `app/Http/Controllers/ControladorHome.php:27-46`

20 líneas. Carga:

- `$ediciones = Edicion::all()` — para el `<select>`.
- `$idEdicion = $request->query('idEdicion')`; `$ultimaEdicion = Edicion::latest('id')->first()` **solo si `$idEdicion` es null**.
- `$EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null`.
- `$idCategoria` — leído y pasado a la vista, **pero la vista nunca lo usa** (código muerto).
- `$categorias` — de la edición seleccionada o de la última, `orderBy nombreCategoria desc`.
- `$roles = User::pluck('rol')` — **pluck de TODOS los usuarios en cada request de la home**, y `$roles` **tampoco se usa en `welcome.blade.php`** (grep: 0 usos). Código muerto + query inútil.

**Falta todo para el bloque de próximos partidos**: no carga ni un `Partido`.

### Query propuesta (a validar en design)

Reutilizar la forma del `select` de `fixture()` (`ControladorHome.php:487-497`),
que ya resuelve el join de escudos y nombres:

```php
Partido::select('partidos.*',
        'el.nombre as nombre_local', 'ev.nombre as nombre_visitante',
        'el.foto as foto_local', 'ev.foto as foto_visitante',
        'f.nombre as nombre_fecha', 'cat.nombreCategoria as nombre_categoria')
    ->join('equipos as el', 'partidos.idEquipoLocal', '=', 'el.id')
    ->join('equipos as ev', 'partidos.idEquipoVisitante', '=', 'ev.id')
    ->join('fechas as f', 'partidos.idFechas', '=', 'f.id')
    ->leftJoin('categorias as cat', 'partidos.idCategoria', '=', 'cat.id')
    ->where('partidos.idEdicion', $idEdicion)
    ->where(fn ($q) => $q->where('partidos.jugado', 0)->orWhereNull('partidos.jugado'))
    ->whereNotNull('partidos.horario_datetime')
    ->where('partidos.horario_datetime', '>=', now())
    ->orderBy('partidos.horario_datetime')
    ->limit(6)
    ->get();
```

El predicado `jugado=0 OR NULL` + `horario >= now()` es **exactamente** el que ya
usa `FixtureExploradorService::resolverFechaRelevante()`
(`app/Services/FixtureExploradorService.php:19-30`) — hay que reutilizar ese
criterio, no inventar otro, para que home y fixture no se contradigan.

**Riesgo real (ver §6): la edición 10 tiene "fixture terminado en varias
categorías" según el encargo. Si no hay partidos futuros, la query devuelve
vacío y el bloque estrella de #100 queda en blanco.** El fallback (últimos
resultados) no es opcional: es parte del diseño.

## 5. Datos disponibles para la tarjeta de partido

De `partidos` (migración + `app/Models/Partido.php:17-30`): `idFechas`,
`idEquipoLocal`, `idEquipoVisitante`, `idGrupo`, `idEdicion`,
`golesEquipoLocal`, `golesEquipoVisitante`, `horario_datetime`, `cancha`,
`jugado`, `idCategoria`, `es_interzonal`, `goles_confirmados_at`, `public_token`.

| Dato pedido | Disponible | Fuente |
| --- | --- | --- |
| Rival / equipos | Sí | join `equipos.nombre` (alias `nombre_local` / `nombre_visitante`) |
| Escudos | Sí | `equipos.foto` → `asset('fotos/equipos/'.$p->foto_local)`. **La columna es `foto`, no `escudo`/`logo`.** Fallback ya convencionado: `onerror` → `Logo_Mitai_SinFondo.png` (`layouts/fixture.blade.php:262`) |
| Fecha y horario | Sí | `horario_datetime` (**nullable** — hay que contemplarlo). Formato del repo: `Carbon::parse(...)->format('d/m/Y · H:i')` (`fixture.blade.php:304`) |
| Cancha | Sí | `partidos.cancha`, renderizado como `Cancha {{ $p->cancha }}` |
| Estado | Sí | `jugado` + comparación de `horario_datetime` con `now()`; `fixture.blade.php:233-258` ya calcula `$esFuturo` y `$statusLabel` ("Pendiente"/...) |
| Nombre de fecha | Sí | join `fechas.nombre` |
| Categoría | Sí | leftJoin `categorias.nombreCategoria` — **necesario en la home**, porque sin filtro de categoría los próximos partidos son de categorías mezcladas |

**Existe una tarjeta de partido ya diseñada** en `layouts/fixture.blade.php:235-311`
(y su gemela interzonal en 366-449). Hoy está **inline y duplicada**, no es un
partial. Extraerla a `resources/views/partials/partido-card.blade.php` (o
componente anónimo `<x-partido-card>`) resolvería a la vez el bloque de la home y
la duplicación del fixture — pero es un refactor que **toca #98 ya mergeado** y
debe declararse explícitamente en la propuesta, no colarse.

## 6. Verificaciones con tinker PENDIENTES (no ejecutadas — sin shell)

Comandos exactos a correr en requirements/design antes de comprometer el diseño:

```bash
# ¿Hay partidos futuros en la edición activa? (el bloque estrella depende de esto)
docker compose exec -T app php artisan tinker --execute='
$id = \App\Models\Edicion::latest("id")->first()->id;
echo "edicion=$id\n";
echo "futuros=".\App\Models\Partido::where("idEdicion",$id)
  ->where(fn($q)=>$q->where("jugado",0)->orWhereNull("jugado"))
  ->whereNotNull("horario_datetime")
  ->where("horario_datetime",">=",now())->count()."\n";
echo "sin_horario=".\App\Models\Partido::where("idEdicion",$id)->whereNull("horario_datetime")->count()."\n";
echo "jugados=".\App\Models\Partido::where("idEdicion",$id)->where("jugado",1)->count()."\n";
echo "proximo=".(\App\Models\Partido::where("idEdicion",$id)
  ->where(fn($q)=>$q->where("jugado",0)->orWhereNull("jugado"))
  ->whereNotNull("horario_datetime")->where("horario_datetime",">=",now())
  ->orderBy("horario_datetime")->value("horario_datetime") ?? "NINGUNO")."\n";'

# ¿Cuántos equipos sin foto? (impacta el diseño de los escudos)
docker compose exec -T app php artisan tinker --execute='
$id = \App\Models\Edicion::latest("id")->first()->id;
echo \App\Models\Equipo::where("idEdicion",$id)->whereNull("foto")->count()."/".
     \App\Models\Equipo::where("idEdicion",$id)->count()."\n";'
```

## 7. Punto de inserción del favorito (#99)

Orden propuesto para la home reestructurada, mobile-first:

1. Alertas de sesión (sin cambios de posición).
2. **Hero mínimo**: nombre de la edición + `<select>` de edición + CTA primario "Ver partidos" + CTA secundario "Seguir mi equipo".
3. **Card del equipo favorito** — `<section id="equipo-seguido">`, **renderizada solo si hay favorito**. En #100 queda como contenedor vacío/placeholder que #99 hidrata.
4. **Próximos partidos** (3-6 tarjetas + "Ver todos" → `fixture`).
5. Carrusel de categorías.
6. Accesos secundarios agrupados: Posiciones · Goleadores · Finales (+ Vallas, que el issue no nombra — ver §9).
7. Historial (Campeones/Subcampeones/Goleadores histórico/Vallas históricas), colapsado o comprimido.
8. Comunidad (3 links de redes) + institucional recortado.
9. Footer.

Si el favorito existe, 3 va arriba de 4; si no existe, 4 sube y el CTA secundario
del hero ancla a la sección de selección. **Esto requiere que #99 esté definido
antes**, o que #100 solo defina el hueco.

## 8. Conteo de texto candidato a eliminación

Solo prosa descriptiva/institucional/kickers (no labels de botones ni nombres de datos):

| Origen | Palabras aprox |
| --- | --- |
| Hero: kicker + `<h1>` + `<p>` + 3 badges | ~50 |
| Card de edición: kicker + `<h2>` + `<p>` + 2 mini-cards | ~40 |
| §4 Accesos directos: kicker + `<h2>` + `<p>` | ~25 |
| §5 Historial: kicker + `<h2>` + `<p>` + 4 descripciones de tarjeta + 4 kickers | ~65 |
| §6 Comunidad/institucional: 2 kickers + `<h2>` + `<h3>` + 2 `<p>` + 4 bullets | ~110 |
| **Total** | **~290 palabras** |

En líneas de Blade: el hero (131) + card de edición (parte de esas) + §6 (79) son
**~210 de las ~470 líneas del archivo**, y ninguna aporta información viva.

## 9. Preguntas abiertas (para requirements)

1. **#99 no existe en la branch.** ¿#100 deja el slot del favorito declarado y vacío, o se absorbe #99? (Bloqueante para el orden de secciones.)
2. **Vallas.** El issue nombra Posiciones/Goleadores/Finales como secundarios y no menciona Vallas, que hoy tiene **dos** accesos (hero `tabla-vallas` + tarjeta histórica `vallas`). ¿Entra en el grupo de secundarios o se elimina del cuerpo?
3. **`goleadores` vs `tabla-goleadores`** (y `vallas` vs `tabla-vallas`): dos rutas distintas con nombres casi iguales conviviendo en la misma página. ¿Se conservan ambas o el histórico queda solo dentro de "Historial"?
4. **Fallback sin partidos futuros**: si la edición activa tiene el fixture terminado, ¿el bloque muestra últimos resultados, el mensaje "torneo finalizado", o se oculta? (Depende de §6.)
5. **¿Cuántos próximos partidos?** ¿3, 5 o 6? ¿Mezclados de todas las categorías o priorizando la del favorito?
6. **Extracción del partial de tarjeta de partido**: ¿se autoriza tocar `layouts/fixture.blade.php` (de #98, ya mergeado) para compartir el componente, o la home duplica el markup?
7. **Alertas y código muerto** (`$roles`, `$idCategoria`, `descargarPDF()`): ¿entran en esta PR como limpieza, o se quedan afuera por disciplina de alcance?

## 10. Riesgos

- **Pint**: `pint --test app/Http/Controllers/ControladorHome.php` **ya falla en `main`** por deuda preexistente de imports. Scopear a `--dirty` y no reportarlo como regresión.
- **Tamaño**: el explore paraguas estimaba ~250-380 líneas para #100. Con reescritura de hero + nueva sección + controlador, es realista, pero roza el límite de 400 líneas de una PR reviewable → evaluar split (backend/query primero, vista después), como ya se hizo en `fixture-explorador`.
- **`welcome.js`** se carga vía `head.blade.php:38` dentro de `$assetsVite`. Cualquier JS nuevo del bloque va ahí; no agregar `<script>` inline.
- **Nav sticky de #97**: el hero cambia de altura; verificar que `#nav-centinela` y la clase `.nav-compacta` sigan comportándose bien en 390px.
- **Archivos ajenos** (`.gitignore`, `opencode.json`, `sync-db.sh`, `.codegraph/`, `.pi/`): no tocar ni contar.

## 11. Archivos leídos

`resources/views/welcome.blade.php` (completo), `app/Http/Controllers/ControladorHome.php`
(líneas 1-80 y 402-521), `resources/js/welcome.js` (completo),
`app/Services/FixtureExploradorService.php` (completo), `app/Models/Partido.php`,
`app/Models/Equipo.php` (1-45), `resources/views/layouts/navigation.blade.php` (1-40),
`resources/views/layouts/fixture.blade.php` (greps), `routes/web.php` (greps),
`resources/views/head.blade.php` (grep). Único archivo escrito: este.
