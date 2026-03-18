# Especificacion - equipos sin asistencia en panel admin

## Objetivo

Permitir que una persona administradora identifique rapidamente, dentro de `Panel/admin`, los equipos de la edicion seleccionada cuyos jugadores todavia no tienen asistencia cargada.

## Alcance

- El alcance se limita al dashboard de `Panel/admin`.
- La informacion debe respetar la edicion activa del panel.
- La visualizacion debe trabajar sobre los partidos normales que hoy ya se muestran en el dashboard admin para la ultima fecha visible de la edicion.
- Si el panel esta filtrado por horario, el listado de pendientes debe reflejar exactamente ese mismo subconjunto de partidos.

## Fuera de alcance

- No cambia la carga manual de planillas.
- No cambia la logica de asistencia de jugadores.
- No agrega reglas nuevas para instancias finales.
- No requiere cambios de base de datos.

## Actores

- Administrador autenticado con acceso al panel.

## Reglas de negocio

1. Un equipo se considera pendiente de asistencia cuando, para un partido visible en `Panel/admin`, no existe ninguna asistencia marcada en su planilla.
2. La falta de asistencia debe contemplar dos casos validos:
   - no existe planilla generada para ese equipo y partido;
   - existe planilla, pero todos los jugadores siguen con `asistio = false`.
3. El control debe hacerse por equipo dentro de cada partido, por lo que en un mismo partido pueden aparecer pendientes el local, el visitante o ambos.
4. La informacion debe pertenecer unicamente a la edicion seleccionada.
5. La informacion debe mostrarse con suficiente contexto para que la persona administradora pueda corregirla sin buscar manualmente el partido.

## Requerimientos funcionales

1. WHEN una persona administradora ingresa a `Panel/admin` con una edicion seleccionada THEN el sistema SHALL calcular los equipos con asistencia pendiente para los partidos mostrados en el dashboard.
2. WHEN el dashboard admin esta filtrado por horario THEN el sistema SHALL recalcular el listado de pendientes usando solo los partidos visibles para ese horario.
3. WHEN un equipo tenga asistencia pendiente THEN el sistema SHALL mostrar al menos el nombre del equipo, su rival, la categoria, la fecha, el horario y un acceso directo a la planilla del partido.
4. IF no existe ninguna planilla para un equipo dentro del partido visible THEN el sistema SHALL informar ese equipo como pendiente.
5. IF existe planilla pero ningun jugador del equipo tiene asistencia marcada THEN el sistema SHALL informar ese equipo como pendiente.
6. WHEN no existan equipos pendientes en el contexto visible THEN el sistema SHALL mostrar un estado claro indicando que no hay asistencias faltantes en los partidos filtrados.
7. IF no hay edicion seleccionada o no existen partidos visibles para el contexto actual THEN el sistema SHALL evitar mostrar alertas enganosas sobre asistencias pendientes.

## Datos minimos esperados en pantalla

- Nombre del equipo pendiente.
- Rival del partido.
- Categoria.
- Fecha o nombre de fecha.
- Horario del partido.
- Estado del faltante (`sin planilla` o `sin asistencias marcadas`).
- Acceso directo a la planilla correspondiente.

## Criterios de aceptacion

- El administrador puede detectar desde `Panel/admin` que equipos requieren carga de asistencia sin entrar partido por partido.
- El listado coincide con la edicion activa y con el filtro de horario vigente.
- El enlace de cada item permite navegar a la planilla correcta del partido.
- Si todo esta cargado, el panel comunica explicitamente que no hay pendientes.
