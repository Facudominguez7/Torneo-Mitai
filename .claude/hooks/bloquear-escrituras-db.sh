#!/usr/bin/env bash
# Bloquea comandos que escriben en la base de desarrollo local (mitai).
# Los tests con Pest usan mitai_testing y no pasan por acá.
# Es heurístico: frena lo obvio (migraciones, seeders y tinker con escrituras).
set -u

# El hook corre con un PATH mínimo; rg y sd vienen de Homebrew.
export PATH="/home/linuxbrew/.linuxbrew/bin:$HOME/.linuxbrew/bin:$PATH"

denegar() {
    printf '{"hookSpecificOutput":{"hookEventName":"PreToolUse","permissionDecision":"deny","permissionDecisionReason":"%s"}}\n' "$1"
    exit 0
}

# Sin las herramientas el chequeo no es confiable: se bloquea en vez de dejar pasar.
for herramienta in jq rg sd; do
    command -v "$herramienta" >/dev/null 2>&1 || denegar "Bloqueado: falta $herramienta para verificar escrituras en la base de desarrollo."
done

comando=$(jq -r '.tool_input.command // ""')

# `migrate` a secas está permitido (autorizado por el usuario el 2026-10-04); las variantes destructivas no.
if printf '%s' "$comando" | rg -q 'artisan\s+(migrate:(fresh|refresh|reset|rollback)|db:seed|db:wipe)(\s|$|["'"'"'])'; then
    denegar "Bloqueado: este comando modifica la base de desarrollo local. Los tests corren con Pest sobre mitai_testing."
fi

# Request::create() solo arma una petición en memoria (renders de solo lectura).
sin_peticiones=$(printf '%s' "$comando" | sd 'Request::create\s*\(' 'Request::build(')

if printf '%s' "$comando" | rg -q 'artisan\s+tinker' \
    && printf '%s' "$sin_peticiones" | rg -qi '(create|save|update|updateOrCreate|firstOrCreate|delete|forceDelete|insert|upsert|increment|decrement|restore|sync|attach|detach)\s*\(|truncate|DB::statement|DB::unprepared'; then
    denegar "Bloqueado: tinker con escritura sobre la base de desarrollo local. Usá tinker solo para lecturas y Pest para probar escrituras."
fi

exit 0
