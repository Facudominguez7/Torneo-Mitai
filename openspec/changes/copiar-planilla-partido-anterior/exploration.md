## Exploration: copiar-planilla-partido-anterior

### Current State
La edición de planilla vive hoy en `ControladorPlanillaJugador@mostrarPlanilla` + `resources/views/Panel/planilla/show.blade.php` y persiste por `ControladorPlanillaJugador@actualizarJugadores` delegando en `PlanillaJugadorAutosaveService`.

- **Carga inicial por equipo (local/visitante):** al abrir la planilla, se ejecuta `sincronizarPlanilla(...)` para ambos equipos; esto agrega activos de Lista Buena Fe y elimina bajas no activas.
- **Edición actual:** en la vista existen dos formularios separados (`data-player-scope="local"` y `visitante`) con autosave por fila para `numero_camiseta`, `goles`, `asistencia`, `tarjeta_roja`.
- **Acciones masivas actuales:** solo existen botones masivos de asistencia (marcar/desmarcar todos) por equipo. No existe acción de “copiar desde partido anterior”.
- **Concurrencia:** el autosave usa `updated_at` por fila y devuelve `409 conflict` cuando hay desfasaje de versión.

Sobre “partido anterior”, hoy **no hay** una función dedicada para resolverlo. El único antecedente parecido es consulta de “último registro” por `id desc` en otros flujos (ej. autocompletar en Lista Buena Fe), que no considera explícitamente fecha/horario del partido.

### Affected Areas
- `app/Http/Controllers/Panel/ControladorPlanillaJugador.php` — entrypoint de planilla, sincronización inicial local/visitante y endpoint de persistencia.
- `app/Services/PlanillaJugadorAutosaveService.php` — lógica transaccional de actualización y manejo de conflicto por `updated_at`.
- `resources/views/Panel/planilla/show.blade.php` — UI de ambos equipos y botones de acción masiva por scope.
- `app/Http/Requests/Panel/ActualizarPlanillaJugadoresRequest.php` — contrato actual para guardar jugadores.
- `app/Models/Partido.php` + `database/migrations/*partidos*` — campos útiles para ordenar antecedentes (`idFechas`, `horario_datetime`, `horario`, `idEdicion`, `idCategoria`).
- `app/Models/PlanillaJugador.php` + migraciones de constraints — fuente de valores a copiar y restricciones/índices relevantes.
- `tests/Feature/Panel/PlanillaConfirmacionTest.php` — cobertura vigente de autosave, conflicto y render de acciones de planilla.

### Approaches
1. **Acción explícita por equipo (recomendada)** — nuevo endpoint POST “Copiar desde partido anterior” para local o visitante, con botón visible en cada bloque.
   - Pros:
     - Bajo riesgo operativo: acción intencional, no silenciosa.
     - Encaja con el patrón actual por equipo (scope local/visitante).
     - Permite validar negocio antes de persistir (faltantes, duplicados, diferencias).
     - Mantiene intacto el autosave por fila y su compatibilidad JSON/HTML.
   - Cons:
     - Requiere resolver formalmente “partido anterior” con criterio estable.
     - Necesita feedback UX claro cuando no hay antecedente válido.
   - Effort: **Medium**

2. **Autocopiar al abrir planilla si detecta antecedente** — aplicar automáticamente al entrar.
   - Pros:
     - Menos clicks para el operador.
   - Cons:
     - Alto riesgo de cambios silenciosos/inesperados.
     - Complica auditoría operativa y debugging.
     - Puede pisar edición manual reciente sin intención explícita.
   - Effort: **Low/Medium** (técnico), **High** (riesgo de negocio)

3. **Acción explícita con previsualización (dry-run)** — primero mostrar diff (copiar/omitir/conflicto), luego confirmar.
   - Pros:
     - Máximo control y menor ambigüedad.
     - Transparencia frente a duplicados/faltantes.
   - Cons:
     - Más complejidad UI/flujo.
     - Mayor esfuerzo de implementación y testing.
   - Effort: **High**

### Recommendation
Implementar la **Opción 1**: acción explícita por equipo con botón dedicado (“Copiar asistencia y camisetas del partido anterior”).

Definir “partido anterior” con un criterio determinista y compatible con datos legacy:
1. mismo `idEdicion`, mismo `idCategoria`, mismo equipo (`idEquipoLocal` o `idEquipoVisitante`), excluyendo partido actual;
2. priorizar partidos con programación anterior al actual usando:
   - `idFechas` menor; o
   - mismo `idFechas` y `horario_datetime`/`horario` menor;
3. fallback determinista por `id` descendente solo si faltan horarios.

Aplicar copia **solo sobre DNIs que existen en la planilla actual** (ya sincronizada con Lista Buena Fe), para evitar reintroducir bajas. Para cada DNI matcheado copiar `asistio` y `numero_camiseta`; registrar y reportar omitidos/no encontrados.

### Risks
- **Jugadores cambiados entre partidos:** un DNI del partido anterior puede no estar activo hoy; debe omitirse y reportarse.
- **Camisetas duplicadas en destino:** hoy no hay validación fuerte al editar/guardar por autosave para duplicidad de número en todo el equipo; copiar puede introducir conflicto operativo.
- **Datos legacy de horario:** coexistencia de `horario_datetime` y `horario` puede generar selección incorrecta si no se normaliza el orden.
- **Asistencia inconsistente de origen:** si el partido anterior tenía carga parcial/incorrecta, la copia propaga error humano.
- **Concurrencia de operadores:** otro usuario puede estar editando mientras se aplica la copia; se requiere estrategia transaccional + refresco coherente de filas.

### Ready for Proposal
**Yes** — listo para pasar a `sdd-propose` con alcance sugerido:
- endpoint explícito por equipo,
- resolución robusta de partido anterior,
- aplicación por intersección de DNIs,
- respuesta con resumen de aplicados/omitidos/duplicados,
- sin automatismos silenciosos.
