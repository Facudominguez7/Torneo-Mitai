# Design: Copiar planilla del partido anterior

## Technical Approach

Se agrega una acción POST explícita por bloque (`local`/`visitante`) dentro de la misma pantalla de planilla, manteniendo el autosave actual por fila sin mezclar contratos. El controlador seguirá fino: validará un request dedicado, delegará la resolución del antecedente a un servicio específico y devolverá JSON con resumen auditable para que la vista refresque solo las filas afectadas y sus `updated_at`.

## Architecture Decisions

| Decisión | Opciones | Elección y rationale |
|---|---|---|
| Acción de copia | Reusar `actualizarJugadores` vs endpoint dedicado | **Endpoint dedicado `planilla.copiarPartidoAnterior`**. Evita contaminar el contrato del autosave y separa intención masiva de guardado por fila. |
| Resolución del antecedente | Query ad hoc en controlador vs resolver dedicado | **`PlanillaPartidoAnteriorResolver`**. Centraliza regla legacy: mismo `idEdicion`, `idCategoria`, equipo, exclusión del partido actual, `idFechas` asc, luego `horario_datetime`, luego `horario`, y fallback final por `id` desc para desempate estable. |
| Aplicación de la copia | Update masivo SQL vs servicio transaccional por filas | **`CopiarPlanillaPartidoAnteriorService`** con `DB::transaction()` y `lockForUpdate()`. Permite validar intersección por DNI, conflictos de camiseta y refresco consistente de `updated_at`. |
| Concurrencia | Bloqueo global del equipo vs optimistic por fila | **Compatibilidad con optimistic existente**. La copia bloquea filas destino afectadas, persiste y devuelve nuevos `updated_at`; el autosave posterior sigue validando versión por fila. |
| UX | Ejecutar directo vs confirmación explícita | **Confirmación previa + feedback post-ejecución**. Reduce cambios masivos accidentales y deja claro qué se copió y qué quedó omitido/conflictivo. |

## Data Flow

```text
Botón "Copiar partido anterior" (scope local/visitante)
  -> modal/confirm explícito
  -> POST JSON /Panel/planilla/copiar-partido-anterior
  -> Controlador valida request + scope
  -> Resolver encuentra antecedente determinista
  -> Service bloquea filas destino, cruza DNIs y aplica copia
  -> invalida confirmación si hubo cambios reales
  -> responde resumen + filas actualizadas + updated_at nuevos
  -> JS refresca solo scope afectado y mantiene autosave activo
```

La copia solo toca `asistio` y `numero_camiseta`. Si una fila queda con conflicto, se omite esa fila; no se aborta toda la operación salvo error técnico. Laravel recomienda envolver locks pesimistas en transacción, así que la copia seguirá el mismo patrón ya usado por el autosave.

## File Changes

| File | Action | Description |
|---|---|---|
| `resources/views/Panel/planilla/show.blade.php` | Modify | Agregar botón por equipo, confirmación UI, banner/resumen y refresh de datasets `data-player-updated-at` para filas copiadas. |
| `app/Http/Controllers/Panel/ControladorPlanillaJugador.php` | Modify | Incorporar acción JSON de copia y delegar a servicios, preservando `actualizarJugadores` sin cambios de contrato. |
| `app/Http/Requests/Panel/CopiarPlanillaPartidoAnteriorRequest.php` | Create | Validar `partido_id`, `idEdicion`, `tipoPartido`, `horario`, `scope`, `updated_at` opcionales de contexto y CSRF/session normal. |
| `app/Services/PlanillaPartidoAnteriorResolver.php` | Create | Resolver antecedente con orden legacy determinista y estados `ok`, `sin_antecedente`, `ambiguo`. |
| `app/Services/CopiarPlanillaPartidoAnteriorService.php` | Create | Ejecutar copia transaccional, validar DNIs presentes, conflictos de camiseta/valor inválido y devolver reporte detallado. |
| `routes/web.php` | Modify | Registrar `POST /planilla/copiar-partido-anterior`. |
| `tests/Feature/Panel/PlanillaConfirmacionTest.php` | Modify | Cubrir endpoint, UI visible por scope y convivencia con autosave/conflictos. |

## Interfaces / Contracts

```http
POST /Panel/planilla/copiar-partido-anterior
Accept: application/json

partido_id=10&idEdicion=3&tipoPartido=partido&horario=09:00&scope=local
```

```json
200 OK
{
  "status": "ok",
  "message": "Se copiaron datos del partido anterior.",
  "data": {
    "scope": "local",
    "partido_anterior_id": 8,
    "resumen": {"copiados": 6, "omitidos": 2, "conflictos": 1},
    "filas_actualizadas": [{"dni": "40500001", "asistencia": true, "numero_camiseta": 11, "updated_at": "2026-04-19T14:00:00Z"}],
    "omitidos": [{"dni": "40500009", "motivo": "dni_no_presente"}],
    "conflictos": [{"dni": "40500010", "motivo": "camiseta_duplicada"}],
    "confirmacion_invalidada": true
  }
}
```

```json
404 {"status":"no_previous_match","message":"El equipo no tiene un partido anterior válido."}
409 {"status":"ambiguous_previous_match","message":"No se pudo determinar un único antecedente válido."}
409 {"status":"conflict","message":"La planilla cambió mientras se intentaba copiar.","data":{"current":[]}}
500 {"status":"error","message":"No se pudo copiar la planilla."}
```

## Testing Strategy

| Layer | What to Test | Approach |
|---|---|---|
| Feature | Botón/acción por scope | GET `planilla.show` y asserts de visibilidad para local y visitante sin automatismo al cargar. |
| Feature | Resolución legacy | Crear antecedentes con combinaciones de `idFechas`, `horario_datetime` y `horario`; assert del `partido_anterior_id` elegido. |
| Feature | Copia parcial auditable | Verificar copiados, omitidos por DNI ausente y conflictos por camiseta duplicada/valor inválido. |
| Feature | Compatibilidad con autosave | Ejecutar copia, usar `updated_at` devuelto y luego autosave OK; repetir con versión vieja y assert 409. |
| Feature | Errores de dominio | Assert 404 sin antecedente y 409 ambiguo/conflict sin cambios persistidos. |

## Migration / Rollout

No migration required. Rollout directo y reversible porque agrega endpoint/servicios sin alterar el flujo actual de autosave.

## Open Questions

- [ ] Definir si el estado `ambiguous_previous_match` quedará solo como defensa ante datos legacy imposibles o si negocio quiere exponer detalles de candidatos empatados en el mensaje.
