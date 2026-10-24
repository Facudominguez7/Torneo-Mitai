#!/bin/bash
# Observa cambios en archivos PHP/Blade y los sincroniza automáticamente al contenedor.
# El repositorio ya está montado en /var/www/html, así que NO se copia nada:
# solo se valida por metadata de Docker que /var/www/html sea el bind mount de
# ESTE proyecto y se limpia la caché de vistas Blade cuando corresponde.
# Uso: ./docker-watch.sh
# Requiere: inotify-tools (sudo apt-get install -y inotify-tools)

# pwd -P: resolver la raíz física para aceptar la misma invocación vía symlink.
PROJECT_DIR="$(cd "$(dirname "$0")" && pwd -P)"
cd "$PROJECT_DIR" || exit 1

WATCH_DIRS=(
    "$PROJECT_DIR/app"
    "$PROJECT_DIR/resources"
    "$PROJECT_DIR/config"
    "$PROJECT_DIR/routes"
    "$PROJECT_DIR/database"
)

# Verificar por metadata de Docker (no por contenido) que /var/www/html del
# servicio app es un bind mount cuyo origen es exactamente este proyecto.
CONTAINER_ID="$(docker compose ps -q app 2>/dev/null)"
if [[ -z "$CONTAINER_ID" ]]; then
    echo "ERROR: no se pudo obtener el contenedor del servicio 'app' (¿docker compose levantado en $PROJECT_DIR?). Abortando sin copiar." >&2
    exit 1
fi
MOUNTS="$(docker inspect --format '{{range .Mounts}}{{println .Type .Source .Destination}}{{end}}' "$CONTAINER_ID" 2>/dev/null)"
MOUNT_ESPERADO="bind $PROJECT_DIR /var/www/html"
if [[ -z "$MOUNTS" ]] || ! printf '%s\n' "$MOUNTS" | grep -qxF "$MOUNT_ESPERADO"; then
    echo "ERROR: /var/www/html del contenedor NO es un bind mount de '$PROJECT_DIR'. Abortando sin copiar." >&2
    exit 1
fi

echo "Observando cambios en el proyecto (bind mount verificado) → servicio app"
echo "Ctrl+C para detener."
echo ""

FALLO=0

# La sustitución emite una línea centinela RC:<código> al terminar, para
# propagar el fallo de inotifywait (binario ausente o salida con error).
while read -r LINE; do
    if [[ "$LINE" == RC:* ]]; then
        if [[ "${LINE#RC:}" != "0" ]]; then
            echo "ERROR: inotifywait terminó con error (${LINE#RC:}); el monitoreo no estuvo activo." >&2
            FALLO=1
        fi
        break
    fi
    FILEPATH="$LINE"
    # Ignorar directorios y rutas que ya no existen.
    if [[ -d "$FILEPATH" || ! -f "$FILEPATH" ]]; then
        continue
    fi
    # Solo procesar descendientes de las carpetas vigiladas.
    EN_WATCH=0
    for DIR in "${WATCH_DIRS[@]}"; do
        if [[ "$FILEPATH" == "$DIR"/* ]]; then
            EN_WATCH=1
            break
        fi
    done
    if [[ "$EN_WATCH" -ne 1 ]]; then
        continue
    fi
    RELATIVE="${FILEPATH#$PROJECT_DIR/}"

    # El bind mount ya refleja el archivo en el contenedor: nada que copiar.
    if [[ "$FILEPATH" == *.blade.php ]]; then
        if ! docker compose exec -T app php artisan view:clear --quiet; then
            echo "ERROR: falló 'php artisan view:clear' tras cambiar $RELATIVE" >&2
            FALLO=1
            continue
        fi
    fi

    echo "  sync: $RELATIVE"
done < <(
    inotifywait -m -r -e close_write,moved_to,create \
        --format '%w%f' \
        "${WATCH_DIRS[@]}" 2>/dev/null
    echo "RC:$?"
)
exec 3<&-

exit "$FALLO"
