## Exploration: autosave-planilla-por-jugador

### Current State
- La UI de planilla renderiza **dos formularios separados** (local y visitante), ambos contra `route('planilla.actualizarJugadores')` y ambos dependen del submit manual **"Actualizar Todos"**.
- Los campos editables por jugador son `numero_camiseta`, `goles`, `asistencia`, `tarjeta_roja` y viajan como `jugadores[{dni}]` dentro del form.
- El backend ya soporta payload parcial por estructura: `actualizarJugadores(Request $request)` recorre `foreach ($request->jugadores as $dni => $datos)` y actualiza por jugador dentro de `DB::transaction`.
- El controlador detecta cambios reales con `planillaTieneCambiosReales(...)`; si hay cambios, invalida confirmación de goles y sincroniza vinculaciones arbitrales.
- El endpoint hoy responde **redirect HTML** (no JSON), pensado para submit tradicional.

### Affected Areas
- `resources/views/Panel/planilla/show.blade.php` — contiene formularios local/visitante, inputs por fila, botón "Actualizar Todos" y script JS inline.
- `app/Http/Controllers/Panel/ControladorPlanillaJugador.php` — método `actualizarJugadores` (persistencia por jugador + side effects de estadísticas/confirmación).
- `routes/web.php` — endpoint reutilizable `planilla.actualizarJugadores` (`POST /Panel/planilla/actualizar-jugador`).
- `tests/Feature/Panel/PlanillaConfirmacionTest.php` — cobertura existente del endpoint (invalida confirmación y persiste cambios), pero sin cobertura de autosave por eventos de UI.

### Approaches
1. **Autosubmit del formulario completo en cada cambio**
   - Pros: Reusa 100% el flujo actual y no requiere cambiar controlador ni respuesta.
   - Cons: Envía payload masivo por cambio, más riesgo de condiciones de carrera y costo de red; mantiene acople al form completo (incluyendo duplicación mobile/desktop).
   - Effort: Low

2. **POST por fila (jugador) con JS nativo + payload mínimo al endpoint actual**
   - Pros: Cumple requerimiento funcional exacto (persistencia por jugador), reduce payload y riesgo de sobrescribir otros jugadores, mantiene endpoint actual (bajo riesgo).
   - Cons: El endpoint devuelve redirect HTML; para buena UX hay que manejar estados de guardado/errores en frontend y evitar flood (debounce para numéricos).
   - Effort: Medium

3. **Nuevo endpoint JSON específico por jugador (futuro)**
   - Pros: Contrato claro para autosave, manejo de errores más limpio, mejor base para evolución.
   - Cons: Incumple restricción de primera implementación (reutilizar endpoint actual), mayor superficie de cambio y testing.
   - Effort: High

### Recommendation
Implementar **Approach 2** en la primera iteración: autosave por fila usando JS nativo y reutilizando `planilla.actualizarJugadores` con payload mínimo (`jugadores[dni]` + contexto hidden del form).

Secuencia sugerida de bajo riesgo:
1) Capturar `change` en `asistencia` y `tarjeta_roja` (guardado inmediato).
2) Capturar `input` + debounce corto en `numero_camiseta` y `goles`.
3) Enviar solo la fila modificada al endpoint actual con `fetch` + CSRF.
4) Añadir feedback no intrusivo por fila (guardando/ok/error) sin recargar pantalla.
5) Mantener botón "Actualizar Todos" temporalmente como fallback en esta primera entrega (o esconderlo por feature flag si negocio lo exige luego).

### Risks
- **Concurrencia / orden de requests**: múltiples cambios rápidos pueden llegar desordenados; se mitiga con cola por fila o cancelación/debounce.
- **Respuesta redirect del endpoint**: al usar `fetch`, hay que tratar éxito por `response.ok` y no depender de flash session.
- **Ruido de invalidación de confirmación**: cada cambio real invalida confirmación de goles; es comportamiento actual, pero en autosave será más frecuente y debe comunicarse en UX.
- **Cobertura de pruebas de UI**: no hay tests frontend para eventos autosave; conviene reforzar tests feature del payload parcial por jugador y efectos colaterales.

### Ready for Proposal
Yes — hay evidencia suficiente de UI, backend y tests para pasar a `sdd-propose` con alcance acotado, reutilizando endpoint actual y minimizando riesgo.
