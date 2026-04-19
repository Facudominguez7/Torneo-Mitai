# Proposal: Autosave de planilla por jugador

## Intent
Eliminar la dependencia de "Actualizar Todos" en planilla y persistir cambios por fila reutilizando `planilla.actualizarJugadores`, con una primera entrega incremental y segura.

## Scope
### In Scope
- Autosave por fila para `numero_camiseta`, `goles`, `asistencia` y `tarjeta_roja`.
- Reuso del endpoint actual con payload mínimo `jugadores[{dni}]`.
- Detección de conflicto por misma fila usando `updated_at`; respuesta de conflicto y feedback por fila.
- Soporte para edición concurrente en filas distintas sin pisado cruzado.

### Out of Scope
- Nuevo endpoint JSON dedicado.
- Refactor grande de controlador/servicios o rediseño completo de la vista.
- Resolución colaborativa en tiempo real o merge automático de conflictos complejos.

## Capabilities
### New Capabilities
- `planilla-player-autosave`: persistencia inmediata por fila con manejo de estados de guardado y conflicto.

### Modified Capabilities
- None.

## Approach
- Agregar JS nativo en `show.blade.php`: `change` para booleanos y `input` con debounce corto para numéricos.
- Enviar solo contexto + fila editada + `updated_at` conocido.
- Mantener `actualizarJugadores` como punto de entrada; detectar request AJAX/fetch y devolver JSON (`ok|conflict|error`) sin romper submit tradicional.
- En conflicto de misma fila, rechazar guardado fuera de orden, informar estado y permitir recarga/manual retry; filas distintas siguen aisladas por payload mínimo.

## Affected Areas
| Area | Impact | Description |
|------|--------|-------------|
| `resources/views/Panel/planilla/show.blade.php` | Modified | Autosave por fila y feedback visual |
| `app/Http/Controllers/Panel/ControladorPlanillaJugador.php` | Modified | Respuesta dual HTML/JSON y chequeo optimistic concurrency |
| `tests/Feature/Panel/PlanillaConfirmacionTest.php` | Modified | Cobertura payload parcial, JSON y conflicto |

## Tradeoffs
- Reusar endpoint baja riesgo y acelera entrega, pero deja un contrato híbrido HTML/JSON.
- `updated_at` evita pisado silencioso en misma fila; no resuelve colaboración en vivo.

## Risks
| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Requests fuera de orden | Med | Debounce + token temporal por fila + conflicto por `updated_at` |
| Mayor invalidación de confirmación | Med | Feedback explícito en fila y tests de regresión |
| Complejidad en Blade inline | Low | Cambios acotados y sin mover flujo fallback |

## Rollback Plan
Desactivar hooks de autosave en vista y conservar submit manual existente; el endpoint sigue compatible con flujo tradicional.

## Acceptance Criteria
- [ ] Cambiar cualquier campo de una fila persiste sin recarga completa.
- [ ] Dos operadores en filas distintas de la misma planilla no se pisan.
- [ ] Si dos operadores editan la misma fila con base desactualizada, el sistema detecta conflicto y no sobrescribe silenciosamente.
- [ ] El submit manual actual sigue funcionando como fallback.

## Validation Plan
- Feature tests para payload parcial por fila, respuesta JSON exitosa y conflicto por `updated_at` desfasado.
- Regresión sobre invalidación de confirmación de goles y compatibilidad del redirect HTML.
