#!/bin/bash
# Fuerza sincronización de archivos PHP/Blade al contenedor app.
# Usar solo si Docker Desktop no propaga los cambios automáticamente.
# Solución permanente: Settings → General → "Use VirtioFS for file sharing"

set -e

CONTAINER="mitai-app"

echo "Sincronizando app/ y resources/ al contenedor..."
docker cp ./app "$CONTAINER":/var/www/html/
docker cp ./resources "$CONTAINER":/var/www/html/

echo "Limpiando caché de vistas..."
docker exec "$CONTAINER" php artisan view:clear

echo "Listo."
