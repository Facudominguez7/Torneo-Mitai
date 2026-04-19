# Verification Report

**Change**: copiar-planilla-partido-anterior  
**Mode**: Strict TDD  
**Artifact store**: openspec

---

### Completeness
| Metric | Value |
|--------|-------|
| Tasks total | 15 |
| Tasks complete | 15 |
| Tasks incomplete | 0 |

---

### Build & Tests Execution

**Pint gate (required)**: ❌ Failed  
Command: `docker compose exec app ./vendor/bin/pint --test`

Summary:
- Global gate failed with **137 style issues across 345 files**.
- This is a real blocking gate for archive readiness.
- Targeted sanity check on the change files passed:  
  `docker compose exec app ./vendor/bin/pint --test app/Http/Controllers/Panel/ControladorPlanillaJugador.php app/Http/Requests/Panel/CopiarPlanillaPartidoAnteriorRequest.php app/Services/CopiarPlanillaPartidoAnteriorService.php app/Services/PlanillaJugadorAutosaveService.php app/Services/PlanillaPartidoAnteriorResolver.php resources/views/Panel/planilla/show.blade.php routes/web.php tests/Feature/Panel/CopiarPlanillaPartidoAnteriorTest.php tests/Feature/Panel/PlanillaConfirmacionTest.php` → ✅ 9 files passed.

**Tests**: ✅ Passed  
Command: `docker compose exec app php artisan test --compact tests/Feature/Panel/CopiarPlanillaPartidoAnteriorTest.php tests/Feature/Panel/PlanillaConfirmacionTest.php`

Result:
- 20 passed
- 0 failed
- 0 skipped
- Duration: 8.33s

**Coverage**: ➖ Not available  
No coverage tool/capability artifact was available in this verify run.

---

### TDD Compliance
| Check | Result | Details |
|-------|--------|---------|
| TDD Evidence reported | ❌ | No `apply-progress` artifact or `TDD Cycle Evidence` table was found in `openspec/changes/copiar-planilla-partido-anterior/`. |
| All tasks have tests | ⚠️ | There is runtime coverage, but several task expectations remain unproven: confirmation UI, mixed legacy ordering, fallback by `id`, invalid shirt number, and operational copy failure. |
| RED confirmed (tests exist) | ✅ | Related runtime evidence exists in `tests/Feature/Panel/CopiarPlanillaPartidoAnteriorTest.php` and `tests/Feature/Panel/PlanillaConfirmacionTest.php`. |
| GREEN confirmed (tests pass) | ✅ | 20/20 executed tests passed in Docker. |
| Triangulation adequate | ⚠️ | Happy path and stale autosave conflict are covered, but deterministic resolver edge cases are under-tested and one test encodes behavior opposite to the spec. |
| Safety Net for modified files | ⚠️ | Not verifiable without `apply-progress`; existing modified-file safety net evidence is missing. |

**TDD Compliance**: 2/6 checks passed

---

### Test Layer Distribution
| Layer | Tests | Files | Tools |
|-------|-------|-------|-------|
| Unit | 0 | 0 | — |
| Integration / Feature | 20 | 2 | Pest / Laravel HTTP tests |
| E2E | 0 | 0 | not installed / not used |
| **Total** | **20** | **2** | |

---

### Changed File Coverage
Coverage analysis skipped — no coverage tool detected in this verification pass.

---

### Assertion Quality
**Assertion quality**: ✅ All reviewed assertions verify meaningful behavior. No tautologies, ghost loops, or smoke-only tests were found in the change-related test files.

---

### Quality Metrics
**Linter**: ❌ Global Pint gate failing (137 style issues across repository); ✅ change-local Pint check passed on 9 targeted files.  
**Type Checker**: ➖ Not available / not configured for this PHP project.

---

### Spec Compliance Matrix

| Requirement | Scenario | Test | Result |
|-------------|----------|------|--------|
| Acción explícita por equipo | Copia manual por bloque | `tests/Feature/Panel/CopiarPlanillaPartidoAnteriorTest.php > copia asistencia y camiseta...` | ⚠️ PARTIAL |
| Acción explícita por equipo | No hay copia automática al cargar | (none found) | ❌ UNTESTED |
| Selección determinista de partido anterior | Selección con datos legacy mixtos | (none found) | ❌ UNTESTED |
| Selección determinista de partido anterior | Ambigüedad en campos horarios | No compliant test; existing test `rechaza la copia cuando el antecedente es ambiguo` asserts 409 instead of fallback by `id` | ❌ UNTESTED |
| Política de omisiones y conflictos | Copia por intersección de DNIs | `tests/Feature/Panel/CopiarPlanillaPartidoAnteriorTest.php > copia asistencia y camiseta...` | ✅ COMPLIANT |
| Política de omisiones y conflictos | Duplicados de camiseta en destino | `tests/Feature/Panel/CopiarPlanillaPartidoAnteriorTest.php > copia asistencia y camiseta...` | ✅ COMPLIANT |
| Respuesta auditable para continuidad operativa | Resultado con resumen verificable | `tests/Feature/Panel/CopiarPlanillaPartidoAnteriorTest.php > copia asistencia y camiseta...` | ✅ COMPLIANT |
| Coexistencia entre copia por equipo y autosave por fila | Autosave continúa tras copia masiva | `tests/Feature/Panel/CopiarPlanillaPartidoAnteriorTest.php > copia asistencia y camiseta...` + `tests/Feature/Panel/PlanillaConfirmacionTest.php > persiste una fila por autosave...` | ✅ COMPLIANT |
| Coexistencia entre copia por equipo y autosave por fila | Conflicto por versión desactualizada tras copia | `tests/Feature/Panel/CopiarPlanillaPartidoAnteriorTest.php > copia asistencia y camiseta...` + `tests/Feature/Panel/PlanillaConfirmacionTest.php > rechaza un autosave con updated_at desfasado...` | ✅ COMPLIANT |
| Manejo de error y ausencia de antecedente sin degradar autosave | Sin partido anterior disponible | `tests/Feature/Panel/CopiarPlanillaPartidoAnteriorTest.php > rechaza la copia cuando no existe un antecedente válido` | ✅ COMPLIANT |
| Manejo de error y ausencia de antecedente sin degradar autosave | Falla operativa durante la copia | (none found) | ❌ UNTESTED |

**Compliance summary**: 6/11 scenarios compliant

---

### Correctness (Static — Structural Evidence)
| Requirement | Status | Notes |
|------------|--------|-------|
| Botón explícito por equipo y feedback | ⚠️ Partial | Buttons and feedback spans exist for `local`/`visitante`, but there is no confirmation step before executing the mass copy. Click handler calls `handleCopyPreviousMatch(button)` directly. |
| Resolver determinista con soporte legacy | ❌ Missing / incorrect | `PlanillaPartidoAnteriorResolver` sorts deterministically, but it returns `ambiguous_previous_match` whenever two candidates share the same `idFechas` + horario key. This contradicts the spec/design fallback by `id`. |
| Aplicación transaccional y reporte por fila | ✅ Implemented | `CopiarPlanillaPartidoAnteriorService` uses `DB::transaction()`, `lockForUpdate()`, reports `copiados/omitidos/conflictos`, and returns `filas_actualizadas`. |
| Integración con autosave / updated_at | ⚠️ Partial | Updated row versions are returned and the UI refreshes `data-player-updated-at`, but request-level `updated_at` for the copy action is validated and sent from JS yet never enforced by the service. |
| Ausencia/error sin degradar autosave | ⚠️ Partial | No-previous-match and generic error payloads exist, but operational-failure continuity is not runtime-proven. |

---

### Coherence (Design)
| Decision | Followed? | Notes |
|----------|-----------|-------|
| Endpoint dedicado `planilla.copiarPartidoAnterior` | ✅ Yes | Route and controller action are present. |
| Resolver dedicado con fallback por `id` | ⚠️ Deviated | Dedicated resolver exists, but exact ties produce `ambiguous_previous_match` instead of deterministic fallback by `id`. |
| Servicio transaccional con `lockForUpdate()` | ✅ Yes | Implemented in `CopiarPlanillaPartidoAnteriorService`. |
| Compatibilidad con optimistic existing | ⚠️ Deviated | Row `updated_at` refresh after copy is implemented, but copy-action optimistic conflict (`409 conflict`) from design contract is not enforced. |
| Confirmación previa + feedback post-ejecución | ⚠️ Deviated | Feedback exists; confirmation step is missing. |
| File changes table alignment | ✅ Yes | Planned files exist, with tests split into a dedicated feature file plus the existing autosave file. |

---

### Issues Found

**CRITICAL**
- Missing Strict TDD evidence artifact: no `apply-progress` / `TDD Cycle Evidence` found for this change.
- Global required gate `docker compose exec app ./vendor/bin/pint --test` is red (137 style issues / 345 files), so archive readiness is blocked.
- Resolver behavior contradicts spec/design: equal `idFechas` + horario candidates return `ambiguous_previous_match` instead of using deterministic fallback by `id`.
- Spec scenarios remain unproven at runtime: no automatic copy on load, mixed legacy ordering, fallback-by-`id`, and operational copy failure.

**WARNING**
- UX requirement is only partially met: there is feedback after copy, but no explicit confirmation before launching the mass action.
- Copy request validates/sends `updated_at`, but `CopiarPlanillaPartidoAnteriorService` ignores it, so the copy action itself has no optimistic conflict protection.
- Edge-case test coverage is incomplete for `camiseta_invalida`.

**SUGGESTION**
- Add a dedicated feature test that proves the non-target block stays unchanged after copying one scope.
- Add a deterministic resolver dataset covering `horario_datetime`, plain `horario`, null/empty horario, and tie fallback by `id`.
- Add an integration test for operational failure (mock/induce service exception) to prove autosave continuity after a failed copy.

---

### Verdict
**FAIL**

La base funcional principal existe y los tests ejecutados pasan, pero el change NO está listo para archive porque falla un gate obligatorio, falta evidencia Strict TDD, y el resolver actual contradice un requirement central del spec.
