# AGENTS.md
Guia operativa para agentes de codigo en este repositorio.

## 1) Contexto rapido
- Proyecto: Torneo Mitai.
- Stack backend: Laravel 11, PHP 8.3, Eloquent ORM, FormRequest.
- Stack frontend: Blade, Tailwind, Alpine, JavaScript vanilla.
- Build frontend: Vite.
- Testing: Pest sobre PHPUnit.
- Docker disponible con `docker compose`.

## 2) Reglas externas (Cursor/Copilot)
Revision actual del repositorio:
- No existe `.cursorrules`.
- No existe `.cursor/rules/`.
- No existe `.github/copilot-instructions.md`.

Si en el futuro aparecen, esas reglas tienen prioridad sobre este archivo cuando haya conflicto.

## 3) Regla obligatoria: skill Laravel
- Para tareas de Laravel/PHP en este repo, cargar y usar la skill `laravel-specialist`.
- Aplicar sus patrones para Eloquent, validacion, servicios y testing.
- Mantener consistencia con el modulo existente (codigo legacy incluido).

## 4) Regla obligatoria: docstrings en espanol
- Todo codigo nuevo o modificado debe incluir docstrings en espanol.
- Minimo obligatorio en:
  - metodos publicos de controladores,
  - servicios de negocio,
  - middleware nuevo,
  - metodos complejos con reglas no obvias.
- Los docstrings deben explicar el por que y las reglas, no repetir literal el nombre del metodo.

## 5) Setup local
- Instalar dependencias PHP: `composer install`
- Instalar dependencias frontend: `npm install`
- Generar key de app: `php artisan key:generate`
- Ejecutar migraciones: `php artisan migrate`

Notas:
- El repo puede no traer `.env.example`.
- Usar `.env` existente o crear uno segun entorno.
- Verificar credenciales de DB antes de migrar.

## 6) Desarrollo local
- Backend local: `php artisan serve`
- Frontend hot reload: `npm run dev`
- Build de frontend: `npm run build`

## 7) Docker
Inicio rapido:
- `./docker-start.sh`

Flujo manual:
- `docker compose up -d`
- `docker compose exec app php artisan migrate`
- `docker compose exec app php artisan key:generate`

Comandos utiles:
- `docker compose ps`
- `docker compose logs -f app`
- `docker compose exec app php artisan route:list`
- `docker compose exec vite npm run build`

## 8) Lint y formato
PHP:
- Verificar estilo: `./vendor/bin/pint --test`
- Formatear: `./vendor/bin/pint`

Frontend:
- Verificacion practica: `npm run build`

No hay ESLint ni Prettier configurados actualmente.

## 9) Testing (incluye single test)
Suite completa:
- `php artisan test`
- `./vendor/bin/pest`

Por suite:
- `php artisan test --testsuite=Feature`
- `php artisan test --testsuite=Unit`

Un archivo de test:
- `php artisan test tests/Feature/Auth/AuthenticationTest.php`
- `./vendor/bin/pest tests/Feature/ProfileTest.php`

Un test puntual por nombre:
- `php artisan test --filter="users can logout"`
- `./vendor/bin/pest --filter="profile page is displayed"`

Single test en Docker:
- `docker compose exec app php artisan test tests/Feature/Auth/AuthenticationTest.php`
- `docker compose exec app php artisan test --filter="users can logout"`

## 10) Convenciones de formato
- Respetar `.editorconfig`.
- Indentacion: 4 espacios en PHP/Blade.
- EOL: LF.
- Mantener newline final en archivos.
- Evitar reformateos masivos fuera del alcance pedido.

## 11) Imports y estructura de archivos
- Declarar `use` al inicio del archivo.
- Una clase por linea de import.
- Evitar FQCN inline repetidos.
- Seguir el orden de imports ya usado en el archivo/modulo.

## 12) Tipos, firmas y PHP moderno
- En codigo nuevo, tipar parametros y retornos cuando sea razonable.
- Para metodos booleanos, preferir retorno explicito `: bool`.
- Evitar refactor global de tipos en legacy si no fue pedido.
- Mantener compatibilidad con PHP 8.3 y Laravel 11.

## 13) Naming conventions
- Clases: PascalCase.
- Metodos/variables: camelCase.
- Mantener terminos de dominio existentes (mucho negocio en espanol).
- Rutas publicas: kebab-case.
- Nombres de rutas (`->name`): dot notation.

## 14) Controladores y requests
- Mantener controladores finos: coordinan request/response.
- Validar con FormRequest cuando el modulo ya usa ese patron.
- Centralizar reglas en `app/Http/Requests/**`.
- No duplicar validacion entre Request y controlador salvo necesidad real.

## 15) Eloquent y base de datos
- Respetar esquema legacy (`idEdicion`, `idEquipo`, etc.).
- Declarar claves foraneas explicitas cuando no siga convencion Laravel.
- Mantener `fillable` y `casts` al agregar campos.
- Evitar N+1 con `with()` o `loadMissing()`.
- Si hay operaciones multi-tabla, usar `DB::transaction`.

## 16) Servicios y logica de dominio
- Mover logica compleja reusable a `app/Services/**`.
- Metodos pequenos con unica responsabilidad.
- Evitar logica de negocio pesada dentro del controlador.
- Si hay concurrencia, considerar `lockForUpdate` cuando aplique.

## 17) Manejo de errores y logs
- En procesos sensibles usar `try/catch`.
- Loggear con contexto util (ids, accion, causa).
- No loggear secretos ni payloads completos sensibles.
- Para AJAX/API, respuestas consistentes (`success`, `message`, `data`).

## 18) Frontend
- Mantener estructura de vistas por modulo en `resources/views/Panel/**`.
- Reusar parciales `_form.blade.php` cuando corresponda.
- JavaScript nuevo en `resources/js/**` (seguir estilo vanilla actual).
- No introducir frameworks nuevos sin decision explicita del equipo.
- Verificar cambios frontend con `npm run build`.

## 19) Seguridad y operacion
- Nunca commitear `.env`, credenciales o secretos.
- Respetar middlewares `auth`, `verified`, `throttle` y custom.
- Evitar cambios destructivos de datos sin migracion y rollback.
- Asumir que `main` puede disparar deploy productivo.

## 20) Alcance de cambios para agentes
- Limitar cambios al objetivo solicitado por el usuario.
- Evitar refactors globales no pedidos.
- No revertir cambios previos del usuario sin instruccion explicita.
- Priorizar consistencia del modulo por encima de perfeccion teorica.

## 21) Verificacion minima antes de entregar
- Cambio backend chico: ejecutar test puntual por archivo o `--filter`.
- Cambio backend amplio: ejecutar `php artisan test`.
- Cambio estilo PHP: ejecutar `./vendor/bin/pint --test`.
- Cambio frontend: ejecutar `npm run build`.
- Si algo no pudo ejecutarse, reportarlo explicitamente en la entrega.

## 22) Checklist final
1. Cambios acotados al objetivo.
2. Validacion ubicada en la capa correcta.
3. Sin regresiones obvias en rutas, vistas y relaciones.
4. Docstrings en espanol agregados donde corresponde.
5. Skill `laravel-specialist` aplicada en la implementacion.
6. Verificacion minima ejecutada o limitacion informada.
