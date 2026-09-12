# Portal Público Specification

## Purpose

Definir el comportamiento observable de la navegación pública compartida (`layouts/navigation.blade.php`) para que cualquier vista pública del torneo ofrezca acceso directo a las 5 secciones principales, conservando la edición activa y señalando la sección en la que el visitante se encuentra.

## Requirements

### Requirement: Links de sección con edición conservada

La navegación pública MUST mostrar cinco enlaces de sección — Inicio, Partidos, Posiciones, Goleadores y Finales — y cada enlace, cuando exista una edición activa resuelta, MUST incluir el `idEdicion` de esa edición en su URL.

#### Scenario: Guest navega desde Partidos con una edición activa

- GIVEN un visitante sin sesión iniciada visita la vista de Partidos (`fixture`) con un `idEdicion` válido
- WHEN se renderiza la navegación pública
- THEN se muestran los 5 enlaces (Inicio, Partidos, Posiciones, Goleadores, Finales) y cada uno de sus `href` incluye el `idEdicion` de la edición activa

#### Scenario: Posiciones no fuerza una categoría

- GIVEN una edición activa resuelta
- WHEN se renderiza el enlace de Posiciones en la navegación pública
- THEN el enlace incluye `idEdicion` pero no incluye ningún `idCategoria`

### Requirement: Contexto de edición disponible en toda vista pública

La edición activa MUST estar disponible para la navegación pública en cualquier vista que la incluya, incluso cuando el controlador de esa vista no provee explícitamente una edición seleccionada.

#### Scenario: Vista pública sin edición explícita del controlador

- GIVEN una vista pública que no pasa `$EdicionSeleccionada` ni `$ultimaEdicion` a la navegación
- WHEN se renderiza la navegación pública
- THEN la navegación resuelve igualmente una edición activa y los 5 enlaces de sección incluyen su `idEdicion`

#### Scenario: Vista pública con edición explícita del controlador

- GIVEN una vista pública que sí pasa una edición seleccionada explícita
- WHEN se renderiza la navegación pública
- THEN los enlaces usan esa edición explícita, sin ser sobrescrita por ningún valor de respaldo

### Requirement: Resaltado de la sección activa

El enlace correspondiente a la ruta actualmente activa MUST marcarse con `aria-current="page"` y un estilo visual distintivo, mientras que los demás enlaces de sección MUST NOT llevar ese atributo.

#### Scenario: Partidos es la sección activa

- GIVEN un visitante en la ruta `fixture`
- WHEN se renderiza la navegación pública
- THEN el enlace de Partidos tiene `aria-current="page"` y los enlaces de Inicio, Posiciones, Goleadores y Finales no lo tienen

### Requirement: Navegación coherente con el rol del usuario

La navegación pública MUST seguir mostrando las opciones correspondientes al rol del usuario (invitado, autenticado, administrador) sin que la incorporación de los enlaces de sección altere esa lógica existente.

#### Scenario: Invitado ve opciones de acceso

- GIVEN un visitante sin sesión iniciada
- WHEN se renderiza la navegación pública
- THEN se muestran los enlaces de inicio de sesión y registro junto con los 5 enlaces de sección, sin mostrarse opciones de perfil ni de panel de administración

#### Scenario: Usuario autenticado no administrador ve su perfil

- GIVEN un usuario autenticado sin rol de administrador
- WHEN se renderiza la navegación pública
- THEN se muestran el menú de perfil y cierre de sesión junto con los 5 enlaces de sección, sin mostrarse el enlace al panel de administración

#### Scenario: Usuario administrador conserva su acceso

- GIVEN un usuario autenticado con rol de administrador
- WHEN se renderiza la navegación pública
- THEN se muestran los 5 enlaces de sección, el menú de perfil y el enlace al panel de administración

### Requirement: Navegación usable en pantallas angostas

Los 5 enlaces de sección MUST seguir siendo accesibles en pantallas angostas sin provocar desbordamiento horizontal accidental de la página.

#### Scenario: Visitante en pantalla angosta accede a los 5 enlaces

- GIVEN un visitante con un viewport de ancho móvil
- WHEN se renderiza la navegación pública con los 5 enlaces de sección
- THEN los 5 enlaces siguen siendo visibles y accesibles, y la página no genera scroll horizontal fuera del contenedor de la navegación

### Requirement: Navegación sin regresión en layouts existentes

La navegación pública MUST seguir renderizándose sin errores en toda vista que la incluya hoy, incluidas las vistas de dashboard y perfil, y el encabezado del panel de administración MUST permanecer sin cambios de comportamiento.

#### Scenario: Dashboard y perfil siguen respondiendo

- GIVEN un usuario autenticado visita las vistas de dashboard o perfil
- WHEN la página se renderiza
- THEN la respuesta es exitosa y la navegación se muestra sin variables faltantes ni errores

#### Scenario: El panel de administración no se ve afectado

- GIVEN un usuario administrador visita una vista del panel de administración
- WHEN la página se renderiza
- THEN el encabezado del panel de administración se comporta igual que antes de este cambio
