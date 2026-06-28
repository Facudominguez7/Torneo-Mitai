#!/bin/bash
# Observa cambios en archivos PHP/Blade y los sincroniza automáticamente al contenedor.
# Uso: ./docker-watch.sh
# Requiere: inotify-tools (sudo apt-get install -y inotify-tools)

CONTAINER="mitai-app"
PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"

WATCH_DIRS=(
    "$PROJECT_DIR/app"
    "$PROJECT_DIR/resources"
    "$PROJECT_DIR/config"
    "$PROJECT_DIR/routes"
    "$PROJECT_DIR/database"
)

echo "Observando cambios en el proyecto → $CONTAINER"
echo "Ctrl+C para detener."
echo ""

inotifywait -m -r -e close_write,moved_to,create \
    --format '%w%f' \
    "${WATCH_DIRS[@]}" 2>/dev/null |
while read FILEPATH; do
    RELATIVE="${FILEPATH#$PROJECT_DIR/}"
    CONTAINER_PATH="/var/www/html/$RELATIVE"
    CONTAINER_DIR="/var/www/html/$(dirname "$RELATIVE")"

    docker exec "$CONTAINER" mkdir -p "$CONTAINER_DIR" 2>/dev/null
    docker cp "$FILEPATH" "$CONTAINER:$CONTAINER_PATH" 2>/dev/null

    # Limpiar cache de vistas Blade automáticamente
    if [[ "$FILEPATH" == *.blade.php ]]; then
        docker exec "$CONTAINER" php artisan view:clear --quiet 2>/dev/null
    fi

    echo "  sync: $RELATIVE"
done
