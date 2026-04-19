# Design: Autosave de planilla por jugador

## Technical Approach

Se mantiene `planilla.actualizarJugadores` como único POST y se agrega un modo fetch para autosave por fila. La vista `resources/views/Panel/planilla/show.blade.php` enviará solo `jugadores[{dni}]` con los cuatro campos soportados más `updated_at`; el backend responderá JSON cuando `wantsJson()` sea true y conservará redirect HTML para submit tradicional. La persistencia seguirá dentro de transacción, pero la lógica de actualización por fila se moverá a un servicio chico para no seguir engordando el controlador.

## Architecture Decisions

| Decisión | Opciones | Elección y rationale |
|---|---|---|
| Endpoint | Nuevo endpoint JSON vs reutilizar POST actual | **Reutilizar `planilla.actualizarJugadores`**. Cumple spec, reduce riesgo de routing y mantiene clientes legacy. |
| Orquestación backend | Seguir todo en controlador vs extraer servicio | **Extraer `PlanillaJugadorAutosaveService`** para encapsular transacción, side effects y detección de conflicto; deja el controlador como adaptador HTML/JSON. |
| Concurrencia | Lock pesimista global vs optimistic por fila | **Optimistic por `updated_at` de `planilla_jugadores`**. Evita pisado en misma fila sin bloquear filas distintas. |
| Orden de requests UI | Submit inmediato sin control vs debounce global vs cola por fila | **Debounce en numéricos + cola serial por fila**. Mantiene independencia entre DNIs y evita carreras del mismo cliente. |

## Data Flow

```text
input fila DNI X
  -> JS detecta cambio
  -> debounce/queue por DNI
  -> fetch POST planilla.actualizarJugadores (Accept: application/json)
  -> Controlador valida request
  -> Service carga fila + compara updated_at + persiste
  -> JSON ok/conflict/error
  -> UI actualiza estado de esa fila
```

Filas distintas usan colas separadas; por eso local y visitante no se pisan si editan DNIs diferentes. En misma fila, si `updated_at` recibido no coincide con base, el servicio devuelve conflicto y NO aplica cambios.

## File Changes

| File | Action | Description |
|---|---|---|
| `resources/views/Panel/planilla/show.blade.php` | Modify | Quitar ambos botones “Actualizar Todos”, agregar metadatos `data-player-row`, `data-player-updated-at`, nodo de estado por fila y script de autosave por DNI. |
| `app/Http/Controllers/Panel/ControladorPlanillaJugador.php` | Modify | Delegar actualización a servicio, detectar `wantsJson()` y responder contrato dual HTML/JSON. |
| `app/Http/Requests/Panel/ActualizarPlanillaJugadoresRequest.php` | Create | Validar contexto, payload parcial `jugadores`, campos autosave y `updated_at` opcional por fila. |
| `app/Services/PlanillaJugadorAutosaveService.php` | Create | Ejecutar transacción, comparar versión, recalcular estadísticas/goleador e informar resultado estructurado. |
| `tests/Feature/Panel/PlanillaConfirmacionTest.php` | Modify | Cubrir autosave JSON OK, conflicto 409, payload parcial sin pisado cruzado y ausencia del botón. |

## Interfaces / Contracts

```http
POST /Panel/planilla/actualizar-jugador
Accept: application/json
X-Requested-With: XMLHttpRequest

partido_id, idEdicion, tipoPartido, horario, ...contexto
jugadores[40500001][numero_camiseta]=11
jugadores[40500001][goles]=2
jugadores[40500001][asistencia]=1
jugadores[40500001][tarjeta_roja]=0
jugadores[40500001][updated_at]=2026-04-17T22:11:03Z
```

```json
200 OK
{ "status": "ok", "message": "Jugador actualizado correctamente.", "data": { "dni": "40500001", "updated_at": "2026-04-17T22:12:10Z", "confirmacion_invalidada": true } }
```

```json
409 Conflict
{ "status": "conflict", "message": "La fila fue actualizada por otro operador.", "data": { "dni": "40500001", "current": { "numero_camiseta": 9, "goles": 1, "asistencia": true, "tarjeta_roja": false, "updated_at": "2026-04-17T22:12:10Z" } } }
```

```json
422/500
{ "status": "error", "message": "No se pudo guardar la fila." }
```

Si el request no pide JSON, se preserva el redirect actual con flash session.

## Testing Strategy

| Layer | What to Test | Approach |
|---|---|---|
| Feature | Autosave JSON exitoso | POST con `Accept: application/json`, assert `status=ok`, cambios persistidos, `updated_at` renovado e invalidación de confirmación. |
| Feature | Conflicto misma fila | Enviar `updated_at` viejo, assert HTTP 409 + `status=conflict`, sin cambios en DB. |
| Feature | Concurrencia filas distintas | Crear dos `PlanillaJugador`, actualizar solo uno por request parcial y verificar que el otro no cambie. |
| Feature | Regresión UI | GET `planilla.show` y `assertDontSee('Actualizar Todos', false)`, manteniendo acciones de asistencia masiva. |

## Migration / Rollout

No migration required. Rollout directo y reversible: si falla el autosave, el endpoint tradicional sigue aceptando POST completo.

## Open Questions

- [ ] Ninguna bloqueante; el conflicto se resolverá con recarga manual o reintento explícito, sin merge automático.
