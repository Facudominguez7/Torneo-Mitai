# Proposal: Copiar planilla del partido anterior

## Intent
Agregar una acción manual por equipo para copiar `asistencia` y `numero_camiseta` desde el partido anterior del mismo equipo, evitando automatismos silenciosos y sin romper el autosave actual por fila.

## Scope
### In Scope
- Botón explícito en bloque local y visitante.
- Resolución determinista de “partido anterior” con `idEdicion`, `idCategoria`, equipo y orden legacy (`idFechas`, `horario_datetime`, `horario`, fallback por `id`).
- Copia solo por intersección de DNIs ya presentes en la planilla actual.
- Resumen de aplicados, omitidos y conflictos; operación compatible con concurrencia y autosave.

### Out of Scope
- Copia automática al abrir la planilla.
- Previsualización/dry-run con diff previo.
- Resolver o normalizar históricamente datos legacy inconsistentes.
- Reglas avanzadas para merge colaborativo en tiempo real.

## Capabilities
### New Capabilities
- `planilla-copy-previous-match`: acción explícita por equipo para copiar asistencia y camiseta desde un antecedente válido.

### Modified Capabilities
- `planilla-player-autosave`: coexistencia segura entre copia masiva por equipo, autosave por fila y conflictos de concurrencia.

## Approach
- Reusar patrón actual por scope (`local`/`visitante`) con endpoint POST explícito.
- Determinar el antecedente por: mismo torneo/categoría, mismo equipo, excluir actual, priorizar `idFechas` menor; en misma fecha usar `horario_datetime` menor, luego `horario` menor, y `id` descendente como fallback determinista.
- Aplicar copia en transacción solo sobre DNIs existentes hoy; si un DNI no está, se omite. Si el origen tiene camiseta inválida o la copia genera duplicado en destino, no se aplica esa fila y se reporta.
- Responder con filas actualizadas + resumen para refrescar `updated_at` y mantener consistente el autosave posterior.

## Affected Areas
| Area | Impact | Description |
|------|--------|-------------|
| `resources/views/Panel/planilla/show.blade.php` | Modified | Botones por equipo y feedback de resultado |
| `app/Http/Controllers/Panel/ControladorPlanillaJugador.php` | Modified | Acción explícita de copia |
| `app/Services/*Planilla*` | Modified/New | Resolución de antecedente + aplicación transaccional |
| `tests/Feature/Panel/PlanillaConfirmacionTest.php` | Modified | Cobertura funcional y concurrencia |

## Tradeoffs
- Acción explícita reduce sorpresas, pero suma un click.
- Reusar datos legacy evita migraciones, pero obliga a reglas de orden más cuidadosas.

## Risks
| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Antecedente incorrecto por horarios legacy | Med | Orden determinista testeado con casos mixtos |
| Duplicados de camiseta | Med | Omitir fila conflictiva y reportar |
| Ediciones concurrentes | Med | Transacción + refresco de `updated_at` devuelto |

## Rollback Plan
Quitar botones y endpoint de copia; la edición manual/autosave existente sigue operativa sin cambios de datos históricos.

## Acceptance Criteria
- [ ] Existe un botón de copia por equipo y nunca corre en forma automática.
- [ ] El sistema elige el mismo antecedente ante iguales datos legacy.
- [ ] Solo copia DNIs presentes hoy; omitidos/conflictos se reportan.
- [ ] Tras copiar, el autosave por fila sigue funcionando sin pisados silenciosos.

## Validation Plan
- Feature tests para selección de antecedente, omisiones por DNI ausente, duplicados/valores inválidos y compatibilidad con conflicto/autosave.
- Verificación manual de UX: botón por equipo, mensaje resumen y refresh coherente de filas afectadas.
