@AGENTS.md

## Sincronización de memoria con Git

Aplica siempre la sección «Sincronización obligatoria de `.engram/` con Git» de `AGENTS.md`:
- Antes de cada `git push`, guarda la memoria del trabajo, ejecuta `engram sync` desde la raíz del repositorio e incluye los cambios de `.engram/` en un commit autorizado antes de publicar.
- Después de cada `git pull` exitoso, ejecuta `engram sync --import` y consulta `mem_context` antes de continuar.
- Verifica con `engram sync --status`; si la sincronización falla o hay conflictos, detén el flujo y reportalo. No uses `--all` ni `--cloud`, no descartes memoria local y no interpretes esta regla como autorización para commits o acciones remotas.
