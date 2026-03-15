LTI-AGENTE (SDD - FLUJO ESTRICTO)
Todas las tareas deben seguir estrictamente este flujo secuencial. El código es solo un artefacto derivado de la especificación.
1. **Branching & Explorer:** UTILIZA la skill `git-commit` para crear una nueva rama en español para la tarea. Luego, analiza el código y recupera el contexto en Engram.
2. **Requirements (Spec):** Analiza el problema y crea un archivo `docs/spec.md` detallando únicamente el alcance, reglas de negocio y requerimientos funcionales.
3. **Architecture (Design):** Basándote en el spec, crea un archivo `docs/design.md` detallando la solución técnica (modelos, base de datos, controladores, vistas).
🛑 **HUMAN GATE (Punto de Aprobación):** Detente aquí. Pide mi aprobación explícita sobre `docs/spec.md` y `docs/design.md` antes de planificar tareas.
4. **Task Planner:** Una vez aprobado el diseño, crea un archivo `docs/task.md` dividiendo el trabajo en un checklist de tareas atómicas y verificables.
🛑 **HUMAN GATE (Punto de Aprobación):** Detente aquí. Pide mi aprobación del plan de tareas en `docs/task.md`.
5. **Implementer:** Escribe el código (PHP/Blade/Vite) basándose ÚNICAMENTE en el checklist aprobado de `docs/task.md` (ya estás en la rama correcta).
🛑 **HUMAN GATE (Punto de Aprobación):** Detente aquí. Pide mi aprobación para revisar el código.
6. **Verifier:** Ejecuta linting (`./vendor/bin/pint --test`) y pruebas (`php artisan test` o `./vendor/bin/pest`) garantizando usar el contenedor de Docker correspondiente.
7. **Commit, Archiver & Cleanup:** Utiliza la skill `git-commit` para generar el commit final del trabajo, guarda las decisiones en Engram para evitar amnesia. **Finalmente, ELIMINA los archivos temporales `docs/spec.md`, `docs/design.md` y `docs/task.md` (pero NUNCA borres la carpeta `docs/`), y finaliza la sesión.**

## 3. SKILLS REGISTRY Y ROUTER (CARGA DINÁMICA)
Tienes instaladas múltiples skills y servidores MCP. Para evitar la sobrecarga de contexto, TIENES PROHIBIDO asumir reglas sin invocar la herramienta correspondiente ÚNICAMENTE cuando se cumpla la condición:

- **[Condición]:** Tarea de planificación o redacción de especificaciones (archivos dentro de `docs/`).
  👉 **[Ejecutar]:** Usa las skills `spec-driven-development` y `writing-plans`.
- **[Condición]:** Lógica backend, PHP 8.3, controladores o Eloquent ORM.
  👉 **[Ejecutar]:** Usa la skill `laravel-specialist`. (Apóyate en el servidor MCP `context7` para consultar documentación oficial si tienes dudas).
- **[Condición]:** Tareas de frontend, vistas de usuario o UI.
  👉 **[Ejecutar]:** Usa las skills `laravel-blade` y `frontend-design`.
- **[Condición]:** Estilos, maquetación responsive o CSS.
  👉 **[Ejecutar]:** Usa la skill `tailwindcss-advanced-layouts`.
- **[Condición]:** Tareas relacionadas con generación o edición de video.
  👉 **[Ejecutar]:** Usa la skill `remotion-best-practices`.
- **[Condición]:** Tareas relacionadas con el diseño, creación o envío de correos electrónicos.
  👉 **[Ejecutar]:** Usa la skill `resend`.
- **[Condición]:** Control de versiones, creación de ramas al inicio o redacción de commits al final.
  👉 **[Ejecutar]:** Usa la skill `git-commit`.
- **[Condición]:** Necesitas una habilidad de desarrollo que no está en esta lista.
  👉 **[Ejecutar]:** Usa la skill `find-skills` para buscar e instalar conocimiento nuevo.

## 4. CONTEXTO TÉCNICO Y ENTORNO DOCKER LOCAL (Torneo Mitai)
- **Stack Base:** Laravel 11, PHP 8.3, Tailwind CSS, Alpine.js, Vite.
- **Infraestructura Local (Docker):** El entorno está 100% dockerizado. TIENES PROHIBIDO ejecutar comandos de PHP, Composer o Node directamente en la terminal del host. Usa siempre los contenedores correspondientes:
  - 👉 **Backend (PHP/Artisan/Pest):** Ejecuta todo en el servicio `app` (Contenedor: `mitai-app`). 
    *Ejemplo:* `docker compose exec app php artisan make:controller`
  - 👉 **Frontend (NPM/Vite):** Ejecuta todo en el servicio `vite` (Contenedor: `mitai-vite`). 
    *Ejemplo:* `docker compose exec vite npm run build`
- **Servicios expuestos para testing:** Web (`localhost:8000`), phpMyAdmin (`localhost:8080`), Mailhog Web (`localhost:8025`).
- **Base de Datos y Eloquent:** Respetar naming legacy (`idEdicion`, `idEquipo`). Evita N+1 usando `with()`. Usa `DB::transaction` en cambios multi-tabla sensibles.
- **Arquitectura:** Mantén controladores finos (usa `FormRequest`). Mueve la lógica compleja reutilizable a `app/Services/**`.
- **Seguridad:** Nunca loggees secretos ni credenciales.

## 5. REGLAS ESTRICTAS DE IDIOMA Y GIT
- **Idioma Base:** Nombres semánticos de negocio, docstrings y explicaciones estrictamente en **español**.
- **Ramas y Commits OBLIGATORIOS:** Al iniciar CADA nueva tarea, feature o bugfix, estás **OBLIGADO** a crear una rama específica en la fase de Explorer (una sola vez por sesión). Al finalizar y aprobar el código, debes generar un commit.
- **Idioma de Git:** El nombre de la rama (ej. `feature/modulo-pagos`, `bugfix/error-login`) y el mensaje del commit deben estar redactados **exclusivamente en español**. Utiliza obligatoriamente la skill `git-commit` para estructurarlos.

*Actualizar este archivo cuando cambien scripts, herramientas o reglas externas.*
