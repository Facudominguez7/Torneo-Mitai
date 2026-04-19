# planilla-player-autosave Specification

## Purpose

Definir autosave por fila de jugador en planilla, reutilizando el endpoint existente, eliminando el botón verde global de actualización y asegurando concurrencia segura sin romper compatibilidad backend.

## Requirements

### Requirement: Reutilización del endpoint existente

El sistema MUST persistir autosave usando `planilla.actualizarJugadores` como único punto de entrada durante esta migración.

#### Scenario: Autosave usa endpoint actual

- GIVEN una planilla abierta con contexto válido (`idPartido`, equipo, CSRF)
- WHEN el cliente dispara autosave de una fila
- THEN la solicitud se envía a `planilla.actualizarJugadores`
- AND el payload incluye `jugadores[{dni}]` de la fila editada

### Requirement: Autosave por fila completa

El sistema MUST guardar por fila completa cuando cambie cualquiera de estos campos: `numero_camiseta`, `goles`, `asistencia`, `tarjeta_roja`.

#### Scenario: Cambio de campo dispara guardado de fila

- GIVEN una fila de jugador con valores iniciales
- WHEN el operador cambia uno de los cuatro campos permitidos
- THEN el autosave persiste la fila completa de ese `dni`
- AND no requiere submit global ni recarga completa

#### Scenario: Cambios en campos no incluidos no disparan autosave

- GIVEN una edición en un campo fuera del alcance de autosave
- WHEN el operador modifica ese campo
- THEN el sistema MUST NOT iniciar autosave de esta capacidad

#### Scenario: Autosave cubre flujo operativo en local y visitante

- GIVEN una planilla con bloques de jugadores local y visitante visibles
- WHEN el operador modifica un campo soportado en una fila de cualquiera de los dos bloques
- THEN el sistema persiste esa fila por autosave
- AND la operación no depende de un submit global

### Requirement: La UI no expone "Actualizar Todos"

La interfaz de planilla MUST NOT mostrar el botón verde "Actualizar Todos" en los bloques local ni visitante; la UX principal SHALL ser autosave por fila.

#### Scenario: Botón ausente en ambos bloques

- GIVEN la planilla renderizada para edición
- WHEN el operador inspecciona acciones del bloque local y del bloque visitante
- THEN no existe el botón verde "Actualizar Todos" en ninguno de los dos bloques
- AND el operador puede continuar editando con autosave por fila

### Requirement: Concurrencia segura entre filas distintas

El sistema MUST permitir edición concurrente de filas distintas dentro de la misma planilla sin pisado cruzado.

#### Scenario: Dos operadores editan filas distintas

- GIVEN dos operadores sobre la misma planilla
- WHEN cada operador guarda una fila de `dni` diferente
- THEN ambos cambios se persisten exitosamente
- AND ningún guardado sobrescribe datos de la otra fila

### Requirement: Conflicto en misma fila con optimistic concurrency

El sistema MUST validar `updated_at` de la fila al guardar y MUST devolver conflicto verificable cuando la base enviada esté desactualizada.

#### Scenario: Conflicto por `updated_at` desfasado

- GIVEN dos operadores editando el mismo `dni` con distinta versión de `updated_at`
- WHEN el segundo guardado llega con `updated_at` viejo
- THEN la respuesta indica conflicto (`conflict`) y no persiste ese cambio
- AND el cliente puede mostrar estado de conflicto y pedir recarga o reintento manual

#### Scenario: Orden invertido de requests en misma fila

- GIVEN múltiples autosaves de un mismo `dni` en tránsito
- WHEN llega primero una versión más nueva y luego una más vieja
- THEN la versión vieja MUST ser rechazada como conflicto
- AND la versión persistida final corresponde al último estado válido

### Requirement: Compatibilidad backward con submit tradicional

El sistema SHALL mantener compatible el flujo actual de submit manual mientras convivan ambos modos.

#### Scenario: POST tradicional sigue operativo sin botón visible

- GIVEN un cliente legado que envía el formulario completo por POST tradicional
- WHEN el endpoint recibe la solicitud fuera del flujo de autosave
- THEN el endpoint procesa y responde según contrato HTML existente
- AND no se rompe el comportamiento vigente de redirección/fallback

### Requirement: Contrato de respuesta para autosave y errores de red

Para solicitudes de autosave, el sistema MUST responder con estados distinguibles al menos para `ok`, `conflict` y `error`.

#### Scenario: Guardado exitoso de autosave

- GIVEN una solicitud de autosave válida sin conflicto
- WHEN el backend procesa la fila
- THEN la respuesta de autosave indica estado `ok`
- AND el cliente puede marcar la fila como guardada

#### Scenario: Error de red o falla no funcional

- GIVEN una solicitud de autosave que falla por red o error transitorio
- WHEN el cliente no obtiene confirmación `ok`
- THEN el flujo MUST marcar estado de error recuperable en esa fila
- AND la edición local no se considera confirmada hasta reintento exitoso
