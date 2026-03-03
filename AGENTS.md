# AGENTS.md
Guia operativa para agentes de codigo en este repositorio (Torneo Mitai).

## 1) Objetivo
- Entregar cambios pequenos, seguros y consistentes con Laravel 11 + PHP 8.3.
- Priorizar convenciones del modulo por encima de preferencias personales.
- Evitar refactors globales si no fueron pedidos explicitamente.

## 2) Reglas externas (Cursor / Copilot)
Revision actual del repositorio:
- No existe `.cursorrules`.
- No existe `.cursor/rules/`.
- No existe `.github/copilot-instructions.md`.
Si alguno aparece, esas reglas tienen prioridad sobre este archivo.

## 3) Stack del proyecto
- Backend: Laravel 11, PHP 8.3, Eloquent ORM.
- Frontend: Blade, Tailwind CSS, Alpine.js, JavaScript vanilla.
- Bundler: Vite.
- Testing: Pest sobre PHPUnit (`php artisan test` tambien disponible).
- Docker opcional: `docker compose`.

## 4) Reglas obligatorias para agentes
- Para tareas Laravel/PHP, cargar y aplicar la skill `laravel-specialist`.
- Mantener docstrings en espanol donde agreguen contexto de negocio real.
- No agregar comentarios obvios; comentar solo reglas no evidentes.
- No revertir cambios del usuario fuera del alcance pedido.
- No crear commits salvo solicitud explicita del usuario.

## 5) Setup local rapido
- `composer install`
- `npm install`
- `php artisan key:generate`
- `php artisan migrate`
Notas:
- Puede no existir `.env.example`; usar `.env` del entorno.
- Validar DB/SMTP antes de procesos masivos.

## 6) Desarrollo local
- Backend: `php artisan serve`
- Frontend: `npm run dev`
- Build prod frontend: `npm run build`
- Si el usuario pide no ejecutar verificacion pesada, respetar esa instruccion.

## 7) Docker
Inicio rapido:
- `./docker-start.sh`
Flujo manual:
- `docker compose up -d`
- `docker compose exec app php artisan key:generate`
- `docker compose exec app php artisan migrate`
Utilidades:
- `docker compose ps`
- `docker compose logs -f app`
- `docker compose exec app php artisan route:list`
- `docker compose exec vite npm run dev`

## 8) Build / lint / test
PHP formato:
- Verificar: `./vendor/bin/pint --test`
- Formatear: `./vendor/bin/pint`
Frontend:
- Verificar bundle: `npm run build`
- No hay ESLint/Prettier configurados por defecto.
Test completo:
- `php artisan test`
- `./vendor/bin/pest`
Test por suite:
- `php artisan test --testsuite=Feature`
- `php artisan test --testsuite=Unit`
Single test (archivo):
- `php artisan test tests/Feature/Auth/AuthenticationTest.php`
- `./vendor/bin/pest tests/Feature/ProfileTest.php`
Single test (por nombre):
- `php artisan test --filter="users can logout"`
- `./vendor/bin/pest --filter="profile page is displayed"`
Single test en Docker:
- `docker compose exec app php artisan test tests/Feature/Auth/AuthenticationTest.php`
- `docker compose exec app php artisan test --filter="users can logout"`

## 9) Convenciones de formato
- Respetar `.editorconfig`.
- Indentacion: 4 espacios en PHP y Blade.
- EOL: LF y newline final obligatoria.
- Evitar reformateos masivos fuera del alcance del pedido.

## 10) Imports y estructura
- Imports con `use` al inicio del archivo.
- Un import por linea.
- Evitar FQCN inline repetidos.
- Seguir el orden de imports ya usado en cada modulo.
- Una clase principal por archivo (PSR-4).

## 11) Tipos y firmas
- Tipar parametros y retornos en codigo nuevo cuando sea razonable.
- Preferir propiedades tipadas y nullabilidad explicita.
- No hacer refactor global de tipos en legacy sin pedido.
- Mantener compatibilidad con PHP 8.3 y Laravel 11.

## 12) Naming conventions
- Clases: PascalCase.
- Metodos/variables: camelCase.
- Constantes: UPPER_SNAKE_CASE.
- Rutas publicas: kebab-case.
- Nombres de ruta (`->name`): dot notation.
- Mantener terminos de dominio existentes (mucho negocio en espanol).

## 13) Controladores y validacion
- Controladores finos: orquestan request/response.
- Usar FormRequest si el modulo ya sigue ese patron.
- Evitar duplicar validacion entre Request y controlador.
- Para AJAX/API, respuestas JSON consistentes (`success`, `message`, etc.).

## 14) Eloquent y base de datos
- Respetar naming legacy (`idEdicion`, `idEquipo`, etc.).
- Declarar relaciones/claves explicitamente cuando no siga convencion Laravel.
- Evitar N+1 con `with()` y `loadMissing()`.
- Usar `DB::transaction` en cambios multi-tabla sensibles.
- Actualizar `fillable` y `casts` al agregar atributos persistidos.

## 15) Servicios y negocio
- Mover logica compleja reutilizable a `app/Services/**`.
- Mantener metodos pequenos y de responsabilidad unica.
- Evitar logica pesada en Blade y controladores.
- Reusar utilidades privadas antes de duplicar codigo.

## 16) Errores y logging
- Usar `try/catch` en flujos sensibles (SMTP, APIs externas, procesos batch).
- Loggear con contexto util: ids, accion y causa tecnica.
- Nunca loggear secretos, tokens o credenciales.
- Para errores de proveedor externo, devolver estado accionable al frontend.

## 17) Frontend
- Mantener estructura por modulo en `resources/views/Panel/**`.
- Reusar parciales (`_form.blade.php`) cuando existan.
- JS nuevo preferentemente en `resources/js/**`.
- No introducir frameworks nuevos sin aprobacion explicita.
- Mantener UX basica: labels, estados de carga y mensajes claros.

## 18) Seguridad y operacion
- Nunca commitear `.env`, secretos o credenciales.
- Respetar middlewares `auth`, `verified`, `throttle` y custom.
- Evitar cambios destructivos sin migracion y rollback.
- Asumir que ramas principales pueden terminar en despliegue.

## 19) Alcance de cambios
- Cambiar solo lo necesario para el pedido actual.
- No incluir artefactos generados no relacionados.
- Si hay dirty tree, no revertir trabajo ajeno.

## 20) Checklist minimo antes de entregar
1. Cambios acotados al objetivo funcional.
2. Validacion en la capa correcta.
3. `pint --test` ejecutado si esta disponible.
4. Ejecutar al menos un single test o `--filter` cuando sea posible.
5. Reportar explicitamente toda limitacion del entorno.
6. Documentar siguiente paso recomendado si queda riesgo residual.

## 21) Referencias rapidas
- Rutas: `routes/web.php`
- Controladores: `app/Http/Controllers/**`
- Mailables: `app/Mail/**`
- Notificaciones: `app/Notifications/**`
- Vistas panel: `resources/views/Panel/**`
- Tests: `tests/Feature/**`, `tests/Unit/**`

Actualizar este archivo cuando cambien scripts, herramientas o reglas externas.
