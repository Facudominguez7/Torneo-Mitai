# Exploración — `seguir-equipo` (issue #99)

Estado: SOLO exploración. No se implementó nada.

**Limitación importante**: esta sesión de exploración no tuvo herramienta de shell disponible
(solo read/grep/find/write), por lo que **no se pudo ejecutar tinker**. Todas las afirmaciones
de abajo están verificadas contra código y migraciones, no contra datos reales. Las queries
propuestas quedan marcadas como PENDIENTE DE VERIFICACIÓN con el comando exacto a correr.

---

## 1. Modelo `Equipo` (`app/Models/Equipo.php`)

- `$timestamps = false`. Sin `$casts`.
- `$fillable`: `nombre`, `foto`, `idCategoria`, `idEdicion`, `idInstitucion`.
  → **el equipo pertenece directo a una edición y a una categoría** (`equipos.idEdicion`,
  `equipos.idCategoria`). No hace falta pasar por pivot para listar los de la edición.
- Relaciones relevantes: `categoria()` (belongsTo `idCategoria`), `grupos()` (belongsToMany vía
  `equipos_grupos`), `ediciones()` (belongsToMany vía `equipo_ediciones`), `partidosLocales()`,
  `partidosVisitantes()`, `equipoEdiciones()`, `institucion()`.
- **Mutator `setNombreAttribute`**: colapsa espacios y aplica Title Case con mbstring,
  y fuerza siglas `CA/AF/FC/RC` a mayúscula. Por eso los nombres en DB están normalizados
  ("Amigos F.c." → tal cual quedó guardado).
  ⚠️ **Consecuencia para la resolución por nombre**: el nombre guardado en localStorage viene de
  la lista renderizada (ya normalizada), así que un match exacto `where('nombre', $nombre)` debería
  funcionar. Pero conviene ser tolerante: comparar case-insensitive y con espacios colapsados,
  porque el mutator puede cambiar de versión y romper favoritos viejos.
- Hook `booted()` asigna `idInstitucion` automáticamente por `NormalizadorInstituciones::estandarizar()`.
  **`institucion.nombre` es la identidad canónica del club entre ediciones** — es una alternativa
  más robusta que el nombre crudo si más adelante se quiere endurecer el matching (ver Preguntas abiertas).

### Resolución "equipos de la edición"

```php
Equipo::query()
    ->where('idEdicion', $idEdicion)
    ->orderBy('nombre')
    ->get(['id', 'nombre', 'foto', 'idCategoria']);
```

La **edición activa de la home** se resuelve en `ControladorHome::index()` (líneas ~28-42):

```php
$idEdicion = $request->query('idEdicion');
$ultimaEdicion = is_null($idEdicion) ? Edicion::latest('id')->first() : null;
$EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
```

y en la vista se consolida con `@php $edicionActual = $EdicionSeleccionada ?? $ultimaEdicion; @endphp`
(`welcome.blade.php:89-91`). **Usar ese mismo `$edicionActual`** para el bloque nuevo, no re-derivar.

---

## 2. `ControladorHome::index()` y `welcome.blade.php`

`index()` es corto y hoy pasa: `ediciones`, `ultimaEdicion`, `EdicionSeleccionada`, `categorias`,
`idEdicion`, `idCategoria`, `roles`. **No carga equipos.**

Variable nueva sugerida: `$equiposDeLaEdicion` (37 filas → trivial, un solo SELECT con 4 columnas).
Debe tener default en la vista (`$equiposDeLaEdicion ?? collect()`) siguiendo la convención del repo,
porque `welcome` también se renderiza desde tests sin controlador completo.

### Estructura de `welcome.blade.php` (486 líneas)

| Línea | Bloque |
|---|---|
| 3 | `@include('head')` |
| 6 | `@include('layouts.navigation')` (barra sticky unificada) |
| 89-91 | `$edicionActual = $EdicionSeleccionada ?? $ultimaEdicion` |
| 93-219 | **Hero**: fondo cancha + col izq (h1, chips, 4 CTA) + col der (card "Organizá tu experiencia" con `<select name="idEdicion" onchange="this.form.submit()">`) |
| 220-264 | Sección categorías (`@if ($categorias->count() && $edicionActual)`) |
| 265-367 | Sección siguiente (gradiente invertido) |
| 368+ | Sección slate-950 |

### Punto de inserción recomendado

**Inmediatamente después de `</section>` del hero (línea 219), antes de la sección de categorías.**

Razones:
- Es lo primero después del fold; el visitante recurrente lo ve sin scrollear mucho en mobile.
- Está por debajo del selector de edición, así que la relación causal "cambié de edición →
  se recalculó mi equipo" queda visualmente clara.
- No rompe el `@if ($categorias->count())` de la sección siguiente.

Alternativa descartada: meterlo *dentro* de la card del hero (col derecha). Esa card ya tiene
formulario + 2 tiles; agregar el selector la vuelve densa y en mobile queda muy abajo igual.

Patrón visual a reutilizar: `rounded-3xl border border-white/15 bg-gradient-to-br from-white/15
to-white/5 p-6 sm:p-8 shadow-2xl backdrop-blur` (la card del hero). Y para el focus el token del
repo `#f7f280` con `focus-visible`.

---

## 3. Consultas próximo partido / último resultado

### Columnas de `partidos` (verificado en `2025_09_14_114210_create_partidos_table.php`)

- `horario` → `string(250) nullable` (**legacy, texto libre**)
- `horario_datetime` → `dateTime nullable` (**la columna a usar para ordenar/comparar**)
- `horario_datetime` **NO está en `$casts`** del modelo `Partido` (solo `goles_confirmados_at => datetime`).
  Sale como **string** al serializar. Para el JSON hay que formatear explícitamente
  (`Carbon::parse(...)` o agregar el cast — decisión para la propuesta).
- `jugado` → entero/bool; el código existente usa `where('jugado', 1)` y
  `where('jugado', 0)->orWhereNull('jugado')` (hay NULLs reales, por eso el `orWhereNull`).
- Marcador: `golesEquipoLocal` / `golesEquipoVisitante`.
- Otros: `idCategoria`, `idFechas`, `idGrupo`, `idEdicion`, `cancha`, `es_interzonal`,
  `goles_confirmados_at`, `public_token` (auto-UUID en `creating`).

### Precedente canónico: `FixtureExploradorService::resolverFechaRelevante()`

Ya codifica exactamente la semántica de "próximo" y "último" del dominio:

```php
// PRÓXIMO
->where(fn ($q) => $q->where('jugado', 0)->orWhereNull('jugado'))
->whereNotNull('horario_datetime')
->where('horario_datetime', '>=', now())
->orderBy('horario_datetime')->orderBy('idFechas')

// ÚLTIMO
->where('jugado', 1)
->whereNotNull('horario_datetime')
->orderByDesc('horario_datetime')->orderByDesc('idFechas')
```

**Reusar esta semántica tal cual**, agregando el filtro por equipo (local o visitante):

```php
Partido::query()
    ->where('idEdicion', $idEdicion)
    ->where(fn ($q) => $q->where('idEquipoLocal', $idEquipo)
                         ->orWhere('idEquipoVisitante', $idEquipo))
```

El `orWhere` del equipo **debe ir agrupado** (closure), si no se filtra mal junto al `jugado`.

### Comandos tinker a ejecutar (PENDIENTES)

```bash
docker compose exec -T app php artisan tinker --execute='
$e = App\Models\Equipo::where("idEdicion",10)->orderBy("nombre")->first();
dump(["equipo"=>$e->id, "nombre"=>$e->nombre, "idCategoria"=>$e->idCategoria]);
'

docker compose exec -T app php artisan tinker --execute='
$id = 10; $eq = App\Models\Equipo::where("idEdicion",$id)->value("id");
$prox = App\Models\Partido::where("idEdicion",$id)
  ->where(fn($q)=>$q->where("idEquipoLocal",$eq)->orWhere("idEquipoVisitante",$eq))
  ->where(fn($q)=>$q->where("jugado",0)->orWhereNull("jugado"))
  ->whereNotNull("horario_datetime")->where("horario_datetime",">=",now())
  ->orderBy("horario_datetime")->orderBy("idFechas")->first();
dump($prox?->only(["id","idEquipoLocal","idEquipoVisitante","horario","horario_datetime","cancha","idGrupo","idCategoria"]));
'

docker compose exec -T app php artisan tinker --execute='
$id = 10; $eq = App\Models\Equipo::where("idEdicion",$id)->value("id");
$ult = App\Models\Partido::where("idEdicion",$id)
  ->where(fn($q)=>$q->where("idEquipoLocal",$eq)->orWhere("idEquipoVisitante",$eq))
  ->where("jugado",1)->whereNotNull("horario_datetime")
  ->orderByDesc("horario_datetime")->orderByDesc("idFechas")->first();
dump($ult?->only(["id","golesEquipoLocal","golesEquipoVisitante","jugado","horario_datetime"]));
'

# Cuántos partidos con horario_datetime NULL hay en la edicion 10 (riesgo de card vacia)
docker compose exec -T app php artisan tinker --execute='
dump(["total"=>App\Models\Partido::where("idEdicion",10)->count(),
      "sin_horario_dt"=>App\Models\Partido::where("idEdicion",10)->whereNull("horario_datetime")->count(),
      "jugado_null"=>App\Models\Partido::where("idEdicion",10)->whereNull("jugado")->count()]);
'

# Resolución por nombre: unicidad dentro de la edicion
docker compose exec -T app php artisan tinker --execute='
dump(App\Models\Equipo::where("idEdicion",10)->selectRaw("nombre, COUNT(*) c")
  ->groupBy("nombre")->havingRaw("c > 1")->get()->toArray());
'

# Grupo del equipo via pivot
docker compose exec -T app php artisan tinker --execute='
$eq = App\Models\Equipo::where("idEdicion",10)->first();
dump($eq->grupos()->get(["grupos.id","grupos.idCategoria","grupos.idEdicion"])->toArray());
'
```

**⚠️ Riesgo detectado sin verificar**: si `horario_datetime` es NULL en gran parte de la edición
(la columna es nullable y `horario` legacy es string), tanto "próximo" como "último" pueden venir
vacíos. La propuesta necesita un fallback definido (¿ordenar por `idFechas` cuando no hay
`horario_datetime`?). **Esto es lo primero que hay que verificar con tinker.**

---

## 4. Patrón del endpoint JSON público

`app/Http/Controllers/ControladorPlanillaPublica.php` — es el molde exacto a seguir:

```php
public function __construct(private readonly PlanillaPublicaService $planillaPublicaService) {}

public function show(Request $request, string $tipoPartido, string $publicToken): JsonResponse
{
    try {
        return response()->json($this->planillaPublicaService->obtenerPlanilla(...));
    } catch (InvalidArgumentException|ModelNotFoundException $e) {
        return response()->json(['message' => 'La planilla solicitada no existe.'], 404);
    } catch (Throwable $e) {
        Log::error('Error al consultar planilla publica.', [...contexto sin secretos...]);
        return response()->json(['message' => 'No se pudo cargar la planilla en este momento.'], 500);
    }
}
```

Características a replicar:
- Controlador **fino**: constructor promovido con el service `readonly`, tipo de retorno `JsonResponse`.
- Toda la lógica de dominio en `App\Services\*`.
- `response()->json($payload)` plano, **sin headers custom** ni API Resource.
- 404 con `['message' => '...']` en español para "no existe"; 500 genérico con `Log::error`.
- Mensajes de error de negocio en español, sin filtrar la excepción al cliente.

Para `seguir-equipo`: `ControladorEquipoSeguido::show(Request): JsonResponse` +
`EquipoSeguidoService`. El caso "el equipo no existe en esta edición" **no es un 500 ni
necesariamente un 404 de error**: es un estado esperado del flujo (cambio de edición).
Sugerencia para la propuesta: responder `200` con `['encontrado' => false, ...]` para que el
JS renderice el estado "tu equipo no participa en esta edición" sin tratar un error HTTP.
Decisión a validar en Requirements.

Test de referencia existente: `tests/Feature/PlanillaPublicaTest.php`.

---

## 5. Rutas

`routes/web.php` — público, sin prefijo `/api`, kebab-case, con `->name()`:

```
/planillas-publicas/{tipoPartido}/{publicToken}   (JSON)
/tabla-de-posiciones, /tabla-de-goleadores, /tabla-de-vallas, /instancias-finales, /fixture
```

Nombre de ruta propuesto, consistente: **`Route::get('/equipo-seguido', [ControladorEquipoSeguido::class, 'show'])->name('equipo-seguido')`**,
en el bloque público (junto a la línea 55, antes del grupo `auth`). Query params `?nombre=&idEdicion=`.

⚠️ Ojo con el naming del param: el repo usa **`idEdicion`** (camelCase legacy) en TODAS las URLs
públicas, no `edicion`. El brief del parent decía `edicion=Y`; **hay que usar `idEdicion`** para
no romper la convención.

---

## 6. Grupo del equipo (link a tabla de posiciones)

`ControladorHome::tablaPosiciones()` acepta `idEdicion`, `idCategoria`, `idGrupo` y **ya tiene
defaults determinísticos** (líneas 580-600): si falta `idCategoria` toma la primera categoría de
la edición (`orderBy('nombreCategoria','desc')`), y si falta `idGrupo` toma el primer grupo de esa
categoría (`Grupo::where('idCategoria')->where('idEdicion')->orderBy('id')->first()`).

Es decir: **el link funciona con solo `idEdicion` + `idCategoria`**, pero caería en el grupo
equivocado si el equipo no está en el primer grupo. Para apuntar bien hay dos fuentes de `idGrupo`:

1. **Pivot `equipos_grupos`** (`Equipo::grupos()` belongsToMany) — la fuente declarativa.
2. **`partidos.idGrupo`** del próximo/último partido del equipo — ya lo tenemos resuelto en la
   misma consulta, costo cero. Robusto ante pivot incompleto.

Recomendación: usar `partidos.idGrupo` del partido resuelto y caer al pivot si no hay partidos.
`idCategoria` sale de `equipos.idCategoria` directo.

### Link a fixture — hallazgo importante

**`/fixture` YA soporta búsqueda por equipo**: `ControladorHome::fixture()` línea 417 lee
`$request->query('equipo')` y hace búsqueda multi-fecha con LIKE escapado
(`'%'.addcslashes($equipo, '\\%_').'%'`, línea 486). Entonces el acceso directo es simplemente:

```
/fixture?idEdicion={id}&equipo={nombre}
```

**No hay que construir nada nuevo del lado fixture.** Reutilizar la funcionalidad de la PR #116/#117.

---

## 7. Estado de tests

Sí hay red de seguridad sobre la home:

- **`tests/Feature/NavegacionPublicaTest.php`** — 8 tests que hacen `$this->get(route('home'))`
  e inspeccionan el HTML con regex sobre `<nav>`. Incluye aserciones **estrictas de conteo**:
  `preg_match_all('/<a\b[^>]*aria-current="page"/', $nav))->toBe(2)` y
  `substr_count($html, 'aria-label="Navegación principal"')->toBe(1)`.
  ⚠️ **Si el bloque nuevo agrega links con `aria-current` dentro de un `<nav>`, estos tests rompen.**
  El selector de equipo NO debe envolverse en `<nav>` ni usar `aria-current="page"`.
- `tests/Feature/FixtureExploradorTest.php`, `FixtureExploradorVistaTest.php`,
  `TablaPosicionesDefaultTest.php`, `PlanillaPublicaTest.php` (molde para el test del endpoint).

Los tests crean ediciones con `Edicion::create(['nombre' => ...])` mediante el helper
`crearEdicionDePrueba()`. Para el bloque nuevo hará falta crear `Equipo` + `Partido` —
verificar si existen factories (`EquipoFactory`, `PartidoFactory`) o hay que crearlas.

---

## Preguntas abiertas para Requirements/Architecture

1. **`horario_datetime` NULL**: ¿qué porcentaje de partidos lo tiene vacío? Si es alto, hace falta
   un fallback de orden (por `idFechas`) o la card queda vacía para la mayoría. **Bloqueante.**
2. **Respuesta "equipo no existe en esta edición"**: ¿`200` con `encontrado: false` o `404`?
   Afecta cómo el JS distingue "no participa" de "error de red".
3. **Matching de nombre**: ¿match exacto, o case-insensitive + espacios colapsados? ¿Se usa
   `institucion.nombre` como fallback cuando el nombre exacto no está en la edición nueva
   (ej. "Amigos F.c." vs "Amigos FC")? Esto decide si el favorito sobrevive un cambio de edición.
4. **Unicidad del nombre dentro de la edición**: si dos equipos de la misma edición comparten
   nombre (distinta categoría), el nombre solo no alcanza → habría que persistir también
   `idCategoria` en localStorage. Pendiente de verificar con tinker (query provista arriba).
5. **¿Cast de `horario_datetime` en el modelo `Partido`?** Agregarlo es correcto pero toca un
   modelo compartido por fixture/admin/planillas — riesgo de regresión. Alternativa de bajo riesgo:
   formatear solo dentro del `EquipoSeguidoService`.
6. **Clave y versión de localStorage**: definir clave (ej. `mitai:equipoSeguido`) y qué se guarda
   (solo `nombre`; sin datos personales, cumple el AC). ¿Se versiona el payload para poder migrar?
7. **Verificación Playwright (AGENTS.md §8)**: es cambio de UX/UI → obligatorio capturar
   desktop 1280 y mobile 390 antes de reportar listo.

---

## Notas de proceso

- `skill_resolution`: **paths-injected** — se leyó `/home/facundo/.agents/skills/laravel-blade/SKILL.md`.
  Nota: esa skill exige un workflow de agentes (`TeamCreate`, `fuse-ai-pilot:*`, Context7) que no
  aplica en este entorno; se aplicaron solo sus reglas de Blade (escapar con `{{ }}`, `@props`,
  `$attributes->merge()`, preferir componentes anónimos, nada de lógica de negocio en la vista).
- Persistencia: artefacto en este archivo bajo `openspec/changes/seguir-equipo/` y guardado en
  Engram con `topic_key` `sdd/seguir-equipo/explore`.
