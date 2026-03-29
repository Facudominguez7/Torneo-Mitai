# Tasks: Cambio multi-alcance de planillas, push, header y rol arbitral

## Bloque 1: Hardening correctivo de header + push

- [ ] 1.1 Crear la bandera reversible en `config/push.php` y reflejarla en `.env.example`, dejando un unico punto de corte para UI, rutas, service worker y notificaciones.
- [ ] 1.2 Ajustar `resources/views/layouts/navigation.blade.php`, `resources/views/head.blade.php`, `resources/js/push-notifications.js` y `public/sw.js` para ocultar el CTA de push, evitar el doble login guest y servir un worker neutro cuando `push.enabled=false`.
- [ ] 1.3 Endurecer `routes/web.php` y `app/Http/Controllers/ControladorNotificacionesPush.php` para responder como feature apagada sin borrar endpoints ni datos historicos.
- [ ] 1.4 Cortar el despacho de `WebPushChannel` en `app/Notifications/ResultadoCargado.php`, `app/Notifications/NotificacionPersonalizada.php` y `app/Notifications/NuevoPartidoProgramado.php` con la misma bandera.
- [ ] 1.5 Validar el bloque con cobertura en `tests/Feature/` para guest con un solo CTA, rutas push apagadas y notificaciones sin canal webpush; luego correr `./vendor/bin/pint --test` y `php artisan test --filter=Push\|Authentication`.

## Bloque 2: Baseline oficial + transparencia arbitral

- [ ] 2.1 Extraer la logica de `app/Http/Controllers/Panel/ControladorPlanillaJugador.php` a `app/Services/ActualizarPlanillaPartidoService.php`, conservando recalculo e invalidacion via `app/Services/PlanillaConfirmacionService.php`.
- [ ] 2.2 Adaptar `app/Http/Controllers/Panel/ControladorPlanillaJugador.php` y, si hace falta, un `FormRequest` nuevo en `app/Http/Requests/Panel/` para que admin siga guardando sobre el servicio compartido sin cambiar semantica.
- [ ] 2.3 Extender `app/Services/PlanillaPublicaService.php` y el view model asociado para separar `dato reportado por arbitraje` de `dato confirmado/oficial`, incluyendo pendientes de vinculacion sin inferir identidad.
- [ ] 2.4 Actualizar `resources/views/components/planilla-publica-modal.blade.php` y la superficie admin relacionada (`resources/views/Panel/planilla/show.blade.php` y/o cards de estado) para mostrar labels, badges y mensajes de inconsistencia con foco en claridad semantica.
- [ ] 2.5 Reforzar regresion en `tests/Feature/PlanillaPublicaTest.php`, `tests/Feature/Panel/PlanillaConfirmacionTest.php` y el test/admin card que corresponda para cubrir fuente oficial, transparencia arbitral y confirmacion invalida al cambiar goles.

## Bloque 3: Rol `encargado_arbitros` mobile-first

- [ ] 3.1 Agregar una migracion para extender `users.rol`, sumar helpers en `app/Models/User.php` y reemplazar `app/Http/Middleware/UserAccesPanelMiddleware.php` por un middleware parametrizable de roles reutilizable en panel y flujo arbitral.
- [ ] 3.2 Incorporar rutas dedicadas en `routes/web.php` y controladores nuevos en `app/Http/Controllers/Panel/` para `ultima-fecha`, detalle restringido y guardado de goles usando contexto `idEdicion` + `tipoPartido`.
- [ ] 3.3 Implementar la consulta de ultima fecha contextual y filtros en un servicio nuevo de `app/Services/`, limitando resultados por equipo, categoria, zona y estado de carga sin mezclar otras ediciones.
- [ ] 3.4 Crear vistas mobile-first en `resources/views/Panel/arbitros/` para listado y planilla restringida, mostrando dorsal readonly, controles grandes de goles y estados `reportado` vs `oficial`.
- [ ] 3.5 Validar en backend con un `FormRequest` arbitral que solo se acepten cambios sobre `goles`, ignorando o rechazando intentos de editar `numero_camiseta` u otros campos administrativos antes de invocar `ActualizarPlanillaPartidoService`.
- [ ] 3.6 Cubrir `tests/Feature/Panel/` con acceso permitido/denegado, filtros de ultima fecha, persistencia de goles con la misma semantica que admin, rechazo de cambio de dorsal y ausencia de campos prohibidos en la vista mobile.

## Bloque 4: Verificacion final integrada

- [ ] 4.1 Ejecutar `./vendor/bin/pint --test` sobre todo el cambio y corregir estilo antes del cierre.
- [ ] 4.2 Ejecutar `php artisan test --filter=PlanillaPublica\|PlanillaConfirmacion\|Arbitros\|Push` para validar los tres frentes sin hacer build.
