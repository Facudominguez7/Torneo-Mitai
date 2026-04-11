# Plan de ejecución de tests (Docker) — Tabla de Posiciones

Este archivo resume los tests que preparé para el cambio:

1. Al eliminar un equipo en Panel, también se elimina su fila en `tabla_posiciones` para la misma `idEdicion`.
2. CRUD completo de `tabla_posiciones` en Panel Admin + acceso por barra lateral.

---

## 1) Precondiciones

- Tener Docker y Docker Compose funcionando.
- Estar ubicado en la raíz del proyecto (`Torneo-Mitai`).
- Tener levantados al menos estos servicios: `mysql` y `app`.

### Levantar contenedores

```bash
docker compose up -d mysql app
```

> Si necesitás también frontend/nginx para validar UI manual: `docker compose up -d`.

---

## 2) Tests feature del cambio

### A. Bugfix: eliminar equipo limpia tabla de posiciones por edición

```bash
docker compose exec app php artisan test tests/Feature/Panel/ControladorEquipoEliminaTablaPosicionesTest.php
```

### B. CRUD completo de tabla de posiciones (panel)

```bash
docker compose exec app php artisan test tests/Feature/Panel/TablaPosicionCrudTest.php
```

### C. Ambos juntos

```bash
docker compose exec app php artisan test tests/Feature/Panel/ControladorEquipoEliminaTablaPosicionesTest.php tests/Feature/Panel/TablaPosicionCrudTest.php
```

---

## 3) Validación de estilo/código

```bash
docker compose exec app ./vendor/bin/pint --test
```

---

## 4) (Opcional) Suite completa

```bash
docker compose exec app php artisan test
```

---

## 5) Qué validar funcionalmente en UI (manual rápido)

1. Ir a `Panel > Equipos` con una edición activa.
2. Eliminar un equipo que tenga fila en `tabla_posiciones` de esa edición.
3. Confirmar que la fila de `tabla_posiciones` desaparece **solo** para esa `idEdicion`.
4. Ir a `Panel > Tabla de Posiciones` (nuevo acceso en sidebar) y validar:
   - listado,
   - alta,
   - edición,
   - eliminación,
   - conservación de contexto `idEdicion` al redireccionar.

---

## 6) Archivos de tests involucrados

- `tests/Feature/Panel/ControladorEquipoEliminaTablaPosicionesTest.php`
- `tests/Feature/Panel/TablaPosicionCrudTest.php`
