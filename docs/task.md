# Plan de tareas - equipos sin asistencia en panel admin

## Objetivo

Implementar en `Panel/admin` un bloque que informe, para la edicion y el horario visibles, que equipos siguen sin asistencia cargada en sus planillas.

## Checklist de implementacion

- [ ] 1. Extender `app/Services/EstadisticasService.php` con un metodo que resuelva la ultima fecha de la edicion activa y el subconjunto de partidos visibles segun `idEdicion` y `horario`.
- [ ] 2. Agregar en `app/Services/EstadisticasService.php` la consulta agregada sobre `planilla_jugadores` agrupada por `partido_id` e `idEquipo`, calculando `total_planilla` y `total_asistencias` solo para `App\Models\Partido`.
- [ ] 3. Normalizar en `app/Services/EstadisticasService.php` una salida lista para vista que distinga, por cada partido visible, si el local o el visitante estan en estado `sin_planilla` o `sin_asistencias_marcadas`.
- [ ] 4. Garantizar en `app/Services/EstadisticasService.php` retornos seguros cuando no haya edicion seleccionada, ultima fecha o partidos visibles, sin generar falsos pendientes.
- [ ] 5. Actualizar `app/Http/Controllers/ControladorHome.php` para solicitar el nuevo reporte al `EstadisticasService` dentro de `admin()` y exponer la variable `equiposSinAsistencia` a la vista.
- [ ] 6. Mantener en `app/Http/Controllers/ControladorHome.php` la compatibilidad con el filtro horario actual y con el contexto usado para navegar a `planilla.show`.
- [ ] 7. Insertar en `resources/views/Panel/admin.blade.php` un bloque visible en la ruta `admin`, ubicado antes del listado de partidos, con resumen de cantidad de pendientes y contexto de fecha/horario.
- [ ] 8. Renderizar en `resources/views/Panel/admin.blade.php` la lista de equipos pendientes mostrando nombre del equipo, rival, categoria, fecha, horario, condicion, estado del faltante y enlace directo a la planilla del partido.
- [ ] 9. Mostrar en `resources/views/Panel/admin.blade.php` un estado positivo y explicito cuando el contexto actual no tenga equipos pendientes reales.
- [ ] 10. Crear `tests/Feature/Panel/AdminEquiposSinAsistenciaTest.php` con un caso que verifique que un equipo sin planilla aparece como pendiente en el panel admin.
- [ ] 11. Agregar en `tests/Feature/Panel/AdminEquiposSinAsistenciaTest.php` un caso que verifique que un equipo con planilla pero sin asistencias marcadas sigue apareciendo como pendiente.
- [ ] 12. Agregar en `tests/Feature/Panel/AdminEquiposSinAsistenciaTest.php` un caso que verifique que un equipo con al menos una asistencia marcada no aparece en el listado de pendientes.
- [ ] 13. Agregar en `tests/Feature/Panel/AdminEquiposSinAsistenciaTest.php` un caso que verifique que el filtro por horario limita el reporte solo a los partidos visibles en ese contexto.

## Notas de ejecucion

- No se deben crear migraciones ni rutas nuevas.
- La implementacion debe reutilizar el contexto existente de `Panel/admin`.
- La verificacion formal de lint y pruebas completas se realizara en la fase `Verifier` usando Docker.
