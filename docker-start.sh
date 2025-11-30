#!/usr/bin/env bash

echo "🐘 Iniciando Torneos Mita'í con Docker..."
echo ""

# Verificar si Docker está instalado
if ! command -v docker &> /dev/null; then
    echo "❌ Docker no está instalado. Por favor instala Docker Desktop."
    exit 1
fi

# Verificar si Docker Compose (plugin) está disponible
if ! docker compose version &> /dev/null; then
    echo "❌ Docker Compose (plugin) no está instalado o no funciona."
    exit 1
fi

# Copiar .env si no existe
if [ ! -f .env ]; then
    if [ -f .env.docker ]; then
        echo "📝 Copiando .env.docker a .env..."
        cp .env.docker .env
    elif [ -f .env.example ]; then
        echo "📝 Copiando .env.example a .env..."
        cp .env.example .env
    else
        echo "⚠️ No se encontró .env.docker ni .env.example. Debes crear el archivo .env manualmente."
    fi
fi

# Construir e iniciar contenedores
echo "🔨 Construyendo contenedores..."
docker compose build

echo "🚀 Iniciando contenedores..."
docker compose up -d

# Esperar a que MySQL esté listo
echo "⏳ Esperando a que MySQL esté listo..."
sleep 10

# Ejecutar migraciones
echo "🗄️  Ejecutando migraciones..."
docker compose exec app php artisan migrate --force

# Generar key si es necesario
echo "🔑 Generando clave de aplicación..."
docker compose exec app php artisan key:generate

# Crear storage link
echo "🔗 Creando enlace simbólico de storage..."
docker compose exec app php artisan storage:link

# Optimizar aplicación
echo "⚡ Optimizando aplicación..."
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache

echo ""
echo "✅ ¡Aplicación iniciada correctamente!"
echo ""
echo "📍 Accede a tu aplicación en:"
echo "   🌐 Backend: http://localhost:8000"
echo "   🎨 Vite (Hot Reload): http://localhost:5173"
echo ""
echo "🛠️  Comandos útiles:"
echo "   Ver logs: docker compose logs -f"
echo "   Detener: docker compose down"
echo "   Reiniciar: docker compose restart"
echo "   Ejecutar artisan: docker compose exec app php artisan [comando]"
echo "   Acceder al contenedor: docker compose exec app bash"
echo ""
