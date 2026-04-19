# planilla-copy-previous-match Specification

## Purpose

Definir la acción manual por equipo para copiar `asistencia` y `numero_camiseta` desde el partido anterior válido, con reglas legacy deterministas y reporte auditable de resultados.

## Requirements

### Requirement: Acción explícita por equipo

El sistema MUST exponer una acción de copia separada para `local` y `visitante` y MUST NOT ejecutar esta copia de forma automática.

#### Scenario: Copia manual por bloque

- GIVEN una planilla abierta con bloques local y visitante
- WHEN el operador ejecuta “Copiar partido anterior” en un bloque
- THEN el sistema procesa solo ese equipo
- AND el otro bloque no se modifica

#### Scenario: No hay copia automática al cargar

- GIVEN una planilla recién abierta
- WHEN no se ejecuta la acción explícita
- THEN `asistencia` y `numero_camiseta` permanecen sin cambios por esta capacidad

### Requirement: Selección determinista de partido anterior

El sistema MUST resolver el antecedente usando mismo `idEdicion`, misma `idCategoria`, mismo equipo, exclusión del partido actual, y orden: `idFechas` menor, luego `horario_datetime` menor, luego `horario` menor, y fallback determinista por `id`.

#### Scenario: Selección con datos legacy mixtos

- GIVEN múltiples antecedentes con combinación de `horario_datetime` y `horario`
- WHEN se ejecuta la copia
- THEN el sistema selecciona siempre el mismo antecedente según el orden definido

#### Scenario: Ambigüedad en campos horarios

- GIVEN dos antecedentes empatados en fecha y horarios legibles
- WHEN se resuelve el antecedente
- THEN el sistema aplica el fallback por `id` para desempatar sin aleatoriedad

### Requirement: Política de omisiones y conflictos

La copia MUST aplicarse solo a DNIs presentes en la planilla actual, MUST NOT reintroducir jugadores ausentes, y MUST omitir filas con camiseta inválida o duplicada, reportando aplicadas, omitidas y conflictos.

#### Scenario: Copia por intersección de DNIs

- GIVEN un antecedente con jugadores no presentes en la planilla actual
- WHEN se ejecuta la copia
- THEN solo se copian filas cuyo DNI existe en la planilla actual
- AND los DNIs ausentes se reportan como omitidos

#### Scenario: Duplicados de camiseta en destino

- GIVEN una fila origen cuya camiseta produciría duplicado en el equipo destino
- WHEN el sistema evalúa la fila
- THEN esa fila no se aplica
- AND el resumen la reporta como conflicto por duplicado

### Requirement: Respuesta auditable para continuidad operativa

La operación SHOULD devolver filas efectivamente actualizadas y resumen cuantitativo de aplicadas/omitidas/conflictos para trazabilidad funcional.

#### Scenario: Resultado con resumen verificable

- GIVEN una copia ejecutada con mezcla de filas aplicadas y omitidas
- WHEN la operación finaliza
- THEN la respuesta incluye conteos de aplicadas, omitidas y conflictos
- AND permite verificar qué filas quedaron actualizadas
