# Delta for planilla-player-autosave

## ADDED Requirements

### Requirement: Coexistencia entre copia por equipo y autosave por fila

El sistema MUST mantener vigente el autosave por fila después de una copia masiva por equipo, usando el estado más reciente de `updated_at` para evitar pisados silenciosos.

#### Scenario: Autosave continúa tras copia masiva

- GIVEN una copia previa aplicada sobre varias filas de un equipo
- WHEN el operador edita luego una fila y dispara autosave
- THEN el guardado usa la versión actualizada de esa fila
- AND el cambio se persiste sin romper el flujo de autosave

#### Scenario: Conflicto por versión desactualizada tras copia

- GIVEN un cliente con `updated_at` viejo de una fila modificada por copia o por otro operador
- WHEN envía autosave de esa misma fila
- THEN el backend responde conflicto verificable
- AND no persiste el cambio desactualizado

### Requirement: Manejo de error y ausencia de antecedente sin degradar autosave

Cuando la copia no encuentre antecedente o falle la operación, el sistema MUST informar estado de error/ausencia y MUST preservar la edición manual con autosave por fila.

#### Scenario: Sin partido anterior disponible

- GIVEN un equipo sin antecedente válido en edición/categoría
- WHEN el operador intenta copiar
- THEN la respuesta indica ausencia de antecedente sin cambios aplicados
- AND la planilla sigue editable con autosave normal

#### Scenario: Falla operativa durante la copia

- GIVEN una falla transitoria durante la copia
- WHEN la operación termina con error
- THEN se informa estado de error recuperable
- AND el operador puede continuar guardando filas individualmente por autosave
