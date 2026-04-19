# Tasks: Copiar planilla del partido anterior

## Phase 1: Foundation / Contracts

- [x] 1.1 Crear `app/Http/Requests/Panel/CopiarPlanillaPartidoAnteriorRequest.php` con validación de `partido_id`, `idEdicion`, `tipoPartido`, `horario`, `scope` y `updated_at` opcional.
- [x] 1.2 Crear `app/Services/PlanillaPartidoAnteriorResolver.php` con orden determinista legacy: `idFechas` asc, `horario_datetime` asc, `horario` asc y `id` desc como desempate.
- [x] 1.3 Crear `app/Services/CopiarPlanillaPartidoAnteriorService.php` con contrato de reporte por fila (`copiados`, `omitidos`, `conflictos`, `filas_actualizadas`).

## Phase 2: Core Backend Logic

- [x] 2.1 Implementar la copia transaccional con `DB::transaction()` y `lockForUpdate()` sobre filas destino en `CopiarPlanillaPartidoAnteriorService`.
- [x] 2.2 Validar intersección de DNIs presentes hoy y omitir los ausentes sin abortar toda la operación.
- [x] 2.3 Detectar camiseta inválida o duplicada en destino y devolver conflicto por fila sin persistir cambios parciales inválidos.
- [x] 2.4 Refrescar `updated_at` de filas copiadas y devolver payload compatible con autosave/concurrencia.

## Phase 3: UI / Routing / Controller Wiring

- [x] 3.1 Agregar `POST /Panel/planilla/copiar-partido-anterior` en `routes/web.php` con nombre `planilla.copiarPartidoAnterior`.
- [x] 3.2 Extender `app/Http/Controllers/Panel/ControladorPlanillaJugador.php` para recibir el request, delegar al servicio y responder JSON con resumen auditable.
- [x] 3.3 Actualizar `resources/views/Panel/planilla/show.blade.php` con botón explícito por equipo, confirmación previa y feedback visible del resultado.
- [x] 3.4 Refrescar datasets/`data-player-updated-at` de filas copiadas para que el autosave posterior siga detectando versiones válidas.

## Phase 4: Testing / Verification

- [x] 4.1 Agregar feature tests para happy path: antecedente correcto, copia parcial, resumen y `updated_at` nuevo.
- [x] 4.2 Cubrir edge cases: sin antecedente válido, antecedente ambiguo, DNI ausente, camiseta duplicada/inválida y conflicto por versión vieja.
- [x] 4.3 Verificar convivencia con autosave: ejecutar copia, luego autosave exitoso con versión nueva y 409 con versión vieja.
- [x] 4.4 Ejecutar `./vendor/bin/pint --test` y el set mínimo relevante de tests con `php artisan test --compact --filter=PlanillaConfirmacionTest`.
