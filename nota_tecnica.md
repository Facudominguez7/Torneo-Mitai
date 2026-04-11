# Auditoria y rediseno de correo con Resend

## Objetivo

Dejar todo el correo saliente del proyecto usando Resend con una integracion coherente, segura, observable y alineada con buenas practicas actuales de Laravel 11 y Resend, sin depender de SMTP/BCC legacy.

## Restricciones y contexto

- El usuario pidio no leer archivos `.env`.
- El entorno local usa Docker; los comandos de Laravel deben ejecutarse en `app`.
- La documentacion oficial consultada fue:
  - Resend `send-with-laravel` (instalar `resend/resend-laravel`, usar `RESEND_API_KEY`, `MAIL_MAILER=resend`, `RESEND_WEBHOOK_SECRET`).
  - Laravel 11 `mail` y `notifications` (mailers, `from`, `reply_to`, override por notificacion).
- Estado actual del entorno al auditar: `docker compose ps` mostro que el servicio `app` no estaba corriendo, por lo que no pude verificar runtime ni ejecutar tests dentro del contenedor en esta corrida asi que tengo que levantar el proyecto primero.

## Hallazgos del Explorer

### Problemas de configuracion

1. `config/services.php` hoy toma `RESEND_KEY`, pero el usuario configuro `RESEND_API_KEY`.
2. El proyecto no tiene instalado `resend/resend-laravel` ni `resend/resend-php` como dependencia real; en `composer.lock` solo aparece como sugerencia de `laravel/framework`.
3. `config/mail.php` define el mailer `resend`, pero si la clave no resuelve correctamente el flujo real puede caer en otro mailer o fallar en runtime.

### Problemas de arquitectura

1. Conviven dos estrategias de envio:
   - notificaciones Laravel por `notify(..., 'mail')`
   - envios masivos BCC por `Mail::to(...)->bcc(...)->send(...)`
2. El BCC masivo sigue pensado para SMTP/Hostinger y no para Resend.
3. Existe codigo custom de Resend (`ResendMailTransport`, `ResendWebhookController`, `ResendWebhookVerifier`) pero hoy no esta integrado de forma consistente con el resto de la app.

### Problemas de observabilidad

1. `EventServiceProvider` existe pero no esta registrado en `bootstrap/providers.php`.
2. La ruta del webhook de Resend no aparece registrada en `routes/web.php` ni en `routes/auth.php`.
3. `PersistNotificationDispatch` intenta leer el provider id desde `SentMessage::getMessageId()`, pero el transporte oficial de Laravel para Resend guarda el id de Resend en el header `X-Resend-Email-ID`.
4. Los envios BCC no generan trazabilidad por destinatario y no se pueden reconciliar bien con webhooks de entrega, bounce o complaint.

### Problemas de seguridad y deliverability

1. Los enlaces de baja usan token predecible `sha1(email)`.
2. `POST /email/baja` esta excluido de CSRF por RFC 8058, pero el token actual es debil.
3. El sistema no aplica una supresion automatica frente a `email.bounced` o `email.complained`.
4. Se siguen registrando logs con email/nombre completos de destinatarios.
5. Los lotes por BCC empeoran reputacion, observabilidad y control de bajas/quejas.

## Propuesta de alto nivel

Rehacer la integracion de correo para que todo envio salga por Resend usando el paquete oficial de Laravel/Resend, eliminando el camino BCC/SMTP legacy y dejando un unico flujo por destinatario con trazabilidad completa.

### Decision principal

Adoptar `resend/resend-laravel` como integracion oficial y usar Resend como transporte unico de correo saliente.

### Razon

- Alinea el proyecto con la guia oficial actual de Resend.
- Respeta la variable `RESEND_API_KEY` ya configurada por el usuario.
- Reduce codigo custom muerto o inconsistente.
- Facilita webhook oficial y compatibilidad futura.

## Requerimientos funcionales

### RF-01 Transporte unico

WHEN la aplicacion envie cualquier correo THEN el sistema SHALL usar Resend como proveedor unico de salida.

### RF-02 Compatibilidad con flujos actuales

WHEN se dispare un correo de verificacion, recuperacion, anuncio manual, resultado o resumen THEN el sistema SHALL mantener el comportamiento funcional actual para el usuario final.

### RF-03 Sin BCC masivo

WHEN se envien anuncios o resumenes a muchos usuarios THEN el sistema SHALL enviar un correo individual por destinatario y SHALL dejar de usar BCC compartido.

### RF-04 Trazabilidad por destinatario

WHEN un correo sea aceptado por Resend THEN el sistema SHALL persistir el identificador del proveedor por destinatario.

### RF-05 Actualizacion por webhook

WHEN Resend emita eventos `email.sent`, `email.delivered`, `email.bounced`, `email.complained` o `email.failed` THEN el sistema SHALL reconciliar el estado local del despacho correspondiente.

### RF-06 Bajas seguras

WHEN un usuario use el enlace de baja o el one-click unsubscribe THEN el sistema SHALL validar un token firmado/no predecible y SHALL procesar la baja sin exponer cuentas ajenas.

### RF-07 Supresion operativa

WHEN Resend informe bounce permanente o complaint THEN el sistema SHALL bloquear futuros envios a esa direccion hasta revision o rehabilitacion explicita.

### RF-08 Compatibilidad Docker local

WHEN el proyecto se levante en Docker THEN el sistema SHALL poder usar Resend sin depender de SMTP local ni de Mailhog para los correos de aplicacion.

## Requerimientos no funcionales

- Seguridad: verificacion de webhook con `RESEND_WEBHOOK_SECRET` y body crudo.
- Observabilidad: un registro por destinatario y estados sincronizados con webhook.
- Mantenibilidad: controladores finos y logica de correo centralizada en servicios/listeners.
- Privacidad: reducir logs sensibles; no registrar cuerpo completo ni secretos.
- Deliverability: encabezados `from`, `reply_to`, `List-Unsubscribe`, `List-Unsubscribe-Post`, `Feedback-ID`, `List-ID` donde corresponda.

## Diseno tecnico propuesto

### 1. Integracion oficial de Resend

- Instalar `resend/resend-laravel`.
- Mantener `MAIL_MAILER=resend`.
- Configurar `config/services.php` para leer `RESEND_API_KEY` como fuente principal.
- Mantener defaults razonables para `from` y `reply_to` desde `config/mail.php`.

Resultado esperado:
- correos auth y notificaciones usan el mailer oficial
- desaparece la ambiguedad actual entre transporte custom, SMTP y mailer por defecto

### 2. Unificacion de flujos salientes

- Eliminar envio masivo por BCC (`ResumenResultadosFechaMail` + `enviarCorreoBccConFallback`).
- Reemplazar esos flujos por notificaciones o jobs por destinatario.
- Mantener chunking por ids de usuario para no romper el panel admin, pero cada usuario recibe su propio email.

Resultado esperado:
- un despacho real por destinatario
- headers y bajas correctas por usuario
- compatibilidad con estados de webhook

### 3. Trazabilidad correcta de Resend

- Corregir `PersistNotificationDispatch` para tomar el provider id desde `X-Resend-Email-ID` cuando el mailer oficial sea Resend.
- Registrar contexto del envio antes de despachar y persistir el despacho despues del envio con el id real del proveedor.
- Quitar duplicaciones de listeners y registrar `EventServiceProvider` correctamente.

Resultado esperado:
- `notification_dispatches` deja de tener falsos positivos o ids vacios
- el webhook puede reconciliar estados reales

### 4. Webhook oficial y seguro

- Incorporar `RESEND_WEBHOOK_SECRET` como nueva variable requerida.
- Exponer un endpoint de webhook de Resend y excluirlo solo de CSRF.
- Conservar, si conviene por compatibilidad, un alias hacia el path actual esperado por el proyecto/dashboard.
- Procesar eventos de entrega/fallo para actualizar auditoria local.

Resultado esperado:
- firma verificada
- sincronizacion de estados real
- menor codigo custom de seguridad a mantener

### 5. Endurecimiento de bajas y consentimiento

- Reemplazar `sha1(email)` por URLs firmadas o tokens firmados temporales.
- Mantener soporte GET para pagina humana y POST para one-click unsubscribe.
- Asegurar respuesta `200` o `202` en POST conforme a clientes de correo.

Resultado esperado:
- no se puede forjar la baja de otro usuario
- se conserva compatibilidad con Gmail/Outlook

### 6. Supresion por bounce y complaint

- Agregar una capa de supresion de entregabilidad separada de `email_subscribed`.
- Opcion recomendada: nueva tabla `email_suppressions` con email, motivo, provider, timestamps y payload resumido.
- `User::quiereRecibirEmails()` y `scopeSuscritosAEmail()` deben excluir direcciones suprimidas.

Resultado esperado:
- si un correo rebota o genera complaint, la app deja de insistir automaticamente
- la preferencia del usuario y la salud de entregabilidad quedan separadas

### 7. Limpieza tecnica

- Eliminar codigo muerto o inconsistente si queda reemplazado por la integracion oficial:
  - `app/Services/ResendMailTransport.php`
  - `app/Services/ResendWebhookVerifier.php`
  - `app/Http/Controllers/ResendWebhookController.php`
  - logica BCC/SMTP legacy en `ControladorNotificacionesPush`
- Dejar una sola estrategia de envio mantenible.

## Impacto esperado por area

### Auth

- Verificacion de email por codigo y reset de password pasan a salir por Resend de forma consistente.

### Notificaciones del torneo

- Anuncios manuales, resultados y resumenes dejan de compartir un email BCC y pasan a tener trazabilidad individual.

### Admin/panel

- Se mantiene el batching del frontend/backend para no bloquear el panel, pero el envio real es individual por destinatario.

### Seguridad

- Baja segura.
- Webhook firmado.
- Menor exposicion de datos sensibles en logs.

## Variables de entorno necesarias

### Ya existentes segun el usuario

- `RESEND_API_KEY`
- `MAIL_MAILER=resend`
- `MAIL_FROM_ADDRESS`
- `MAIL_FROM_NAME`

### Nueva variable requerida

- `RESEND_WEBHOOK_SECRET`

## Archivos candidatos a tocar en implementacion

- `composer.json`
- `composer.lock`
- `config/services.php`
- `config/mail.php`
- `bootstrap/app.php`
- `bootstrap/providers.php`
- `routes/web.php` o ruta equivalente del webhook
- `app/Providers/EventServiceProvider.php`
- `app/Providers/AppServiceProvider.php`
- `app/Listeners/PersistNotificationDispatch.php`
- `app/Listeners/CacheNotificationDispatchContext.php`
- `app/Listeners/LogNotificacionEnviada.php`
- `app/Http/Controllers/ControladorNotificacionesPush.php`
- `app/Notifications/VerifyEmailCodeNotification.php`
- `app/Notifications/ResultadoCargado.php`
- `app/Notifications/NotificacionPersonalizada.php`
- `app/Notifications/ResumenResultadosFecha.php`
- `app/Models/User.php`
- `app/Mail/ResumenResultadosFechaMail.php` (probable eliminacion)
- migracion y modelo nuevos para supresiones de email
- tests feature/unit de mail, webhook, unsubscribe y fanout

## Riesgos y mitigaciones

### Riesgo 1

La integracion oficial cambie el comportamiento de algunos tests o listeners existentes.

Mitigacion:
- cubrir con tests de integracion y adaptar la extraccion del provider id al header real de Resend.

### Riesgo 2

Los envios masivos por usuario aumenten volumen de jobs.

Mitigacion:
- mantener chunking, usar cola y limitar concurrencia del worker.

### Riesgo 3

El dashboard de Resend tenga un webhook apuntando a otro path.

Mitigacion:
- mantener compatibilidad temporal o documentar claramente el nuevo path.

## Validacion planificada luego de implementar

- `docker compose exec app ./vendor/bin/pint --test`
- `docker compose exec app php artisan test`
- prueba feature del webhook firmado
- prueba feature de baja one-click segura
- prueba feature de persistencia de `notification_dispatches` con provider id real
- prueba feature de no uso de BCC legacy

## Punto de aprobacion

Antes de escribir codigo, necesito aprobacion explicita sobre este diseno. Si se aprueba, el siguiente paso es descomponerlo en tareas atomicas y recien despues implementar.

## Task Planner

### Tarea 1 - Base oficial de Resend

- [ ] Agregar la dependencia oficial `resend/resend-laravel` en `composer.json`.
- [ ] Actualizar `config/services.php` para leer `RESEND_API_KEY` como clave principal y `RESEND_KEY` solo como fallback temporal.
- [ ] Incorporar `RESEND_WEBHOOK_SECRET` y parametros asociados en `config/services.php`.
- [ ] Registrar correctamente `App\Providers\EventServiceProvider::class` en `bootstrap/providers.php`.
- [ ] Revisar `AppServiceProvider` para eliminar registro duplicado de listeners y dejar solo bootstrap relacionado a correo que siga siendo necesario.

### Tarea 2 - Auditoria correcta por destinatario

- [ ] Corregir `app/Listeners/PersistNotificationDispatch.php` para tomar el id real de Resend desde `X-Resend-Email-ID` cuando el mailer oficial lo adjunte.
- [ ] Mantener `app/Listeners/CacheNotificationDispatchContext.php` como fuente de contexto por destinatario.
- [ ] Reducir o sanear `app/Listeners/LogNotificacionEnviada.php` para no registrar datos personales innecesarios.
- [ ] Cubrir estos cambios con pruebas feature/unit de persistencia de `notification_dispatches`.

### Tarea 3 - Webhook firmado y operativo

- [ ] Exponer la ruta webhook de Resend en el proyecto y excluir solo esa ruta del CSRF cuando corresponda.
- [ ] Reemplazar o adaptar la implementacion actual para verificar firma con `RESEND_WEBHOOK_SECRET` usando el body crudo.
- [ ] Actualizar estados locales de `NotificationDispatch` frente a `email.sent`, `email.delivered`, `email.bounced`, `email.complained` y `email.failed`.
- [ ] Agregar pruebas feature de webhook valido e invalido.

### Tarea 4 - Baja segura y consentimiento

- [ ] Reemplazar los links con `sha1(email)` por URLs firmadas/no predecibles.
- [ ] Adaptar `GET /email/baja` y `POST /email/baja` para validar firma correctamente.
- [ ] Mantener compatibilidad con one-click unsubscribe respondiendo `200` en POST.
- [ ] Actualizar notificaciones y mailables que hoy construyen enlaces de baja inseguros.
- [ ] Agregar pruebas feature del flujo de baja segura y del rechazo neutral ante firma invalida.

### Tarea 5 - Supresion por bounce y complaint

- [ ] Crear estructura persistente para supresiones de email.
- [ ] Excluir emails suprimidos desde `User::quiereRecibirEmails()` y `scopeSuscritosAEmail()`.
- [ ] Alimentar esa supresion desde eventos `email.bounced` y `email.complained`.
- [ ] Agregar pruebas unit/feature de supresion automatica.

### Tarea 6 - Eliminar BCC/SMTP legacy

- [ ] Reemplazar `ResumenResultadosFechaMail` y `enviarCorreoBccConFallback()` por envios individuales usando notificaciones o jobs por destinatario.
- [ ] Actualizar los endpoints admin que hoy envian manuales/resumenes por lotes para que sigan procesando chunks de usuarios pero con fanout individual.
- [ ] Eliminar logica de rate limit y errores propios de Hostinger SMTP que ya no aplica al camino principal.
- [ ] Asegurar que anuncios manuales, resumenes y resultados usen un flujo coherente de correo por usuario.
- [ ] Agregar pruebas que confirmen que ya no se usa BCC compartido.

### Tarea 7 - Limpieza final y verificacion

- [ ] Eliminar codigo custom de Resend que quede reemplazado por la integracion oficial.
- [ ] Ejecutar `docker compose exec app ./vendor/bin/pint --test`.
- [ ] Ejecutar `docker compose exec app php artisan test`.
- [ ] Revisar resultados y detenerse en el segundo punto de aprobacion humana antes de la fase de revision final.
