# Tasks: Autosave de planilla por jugador

## Phase 1: Backend foundation

- [x] 1.1 Crear `app/Http/Requests/Panel/ActualizarPlanillaJugadoresRequest.php` con validación del contexto, `jugadores` parcial y `updated_at` opcional por fila.
- [x] 1.2 Crear `app/Services/PlanillaJugadorAutosaveService.php` para transacción, control de versión por `updated_at`, persistencia de la fila y contrato de resultado `ok|conflict|error`.

## Phase 2: Controller and view wiring

- [x] 2.1 Actualizar `app/Http/Controllers/Panel/ControladorPlanillaJugador.php` para delegar al service y responder JSON/409 cuando `wantsJson()` sin romper el redirect HTML.
- [x] 2.2 Limpiar `resources/views/Panel/planilla/show.blade.php` para eliminar ambos botones verdes “Actualizar Todos” y exponer metadatos/estado por fila (`data-*`).
- [x] 2.3 Agregar en `show.blade.php` el JS nativo de autosave por DNI con cola por fila, debounce en numéricos y guardado inmediato en `change` para `asistencia` y `tarjeta_roja`.

## Phase 3: Conflict handling and UI states

- [x] 3.1 Completar el flujo de conflicto en backend: rechazar `updated_at` viejo, devolver la versión actual de la fila y mantener los side effects solo tras guardado exitoso.
- [x] 3.2 Implementar estados visuales por fila en la vista: guardando, guardado, conflicto y error, con retry/recarga manual.

## Phase 4: Tests and verification

- [x] 4.1 Extender `tests/Feature/Panel/PlanillaConfirmacionTest.php` con autosave JSON exitoso, fallback HTML redirect y payload parcial por fila.
- [x] 4.2 Agregar cobertura de conflicto por `updated_at` desfasado (HTTP 409 + `status=conflict`) sin escribir cambios en DB.
- [x] 4.3 Agregar regresión de concurrencia para demostrar que dos filas distintas se actualizan independiente y simultáneamente, y que “Actualizar Todos” ya no se renderiza.
- [ ] 4.4 Verificar con `./vendor/bin/pint --test` y el filtro mínimo relevante de Pest para este cambio.
