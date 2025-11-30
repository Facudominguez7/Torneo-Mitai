#!/bin/bash

echo "🔄 Sincronizacion de Base de Datos (Hostinger -> Docker Local)"
echo "============================================================"
echo ""

# Verificar si existe .env
if [ ! -f .env ]; then
    echo "❌ No se encontro el archivo .env"
    exit 1
fi

# Cargar variables del .env (solo las necesarias)
set -a
[ -f .env ] && . .env
set +a

# Verificar configuración remota
if [ -z "$REMOTE_DB_HOST" ]; then
    echo "❌ Error: REMOTE_DB_HOST no configurado en .env"
    exit 1
fi

echo "📡 Conectando a: $REMOTE_DB_HOST"
echo "📂 Base de datos: $REMOTE_DB_DATABASE"
echo ""
echo "⚠️  ADVERTENCIA: Esto SOBREESCRIBIRA tu base de datos local '$DB_DATABASE'."
read -p "¿Estas seguro de continuar? (S/N): " CONFIRM

if [[ "$CONFIRM" != "S" && "$CONFIRM" != "s" ]]; then
    echo "❌ Cancelado."
    exit 0
fi

echo ""
echo "⏳ Paso 1/2: Descargando datos de Hostinger..."
echo "   (Esto puede tardar dependiendo de tu conexion)"

# 1. Descargar dump
# Nota: Usamos docker compose exec para usar el cliente mysql del contenedor, 
# pero para mysqldump a veces es mejor usar el del host si está, o el del contenedor si no.
# Aquí asumimos que el contenedor 'mysql' tiene mysqldump.
docker compose exec -T mysql mysqldump -h $REMOTE_DB_HOST -P $REMOTE_DB_PORT -u $REMOTE_DB_USERNAME -p$REMOTE_DB_PASSWORD $REMOTE_DB_DATABASE --no-tablespaces --column-statistics=0 --single-transaction --quick --lock-tables=false > temp_sync.sql

if [ $? -ne 0 ]; then
    echo ""
    echo "❌ ERROR CRITICO: No se pudo descargar la base de datos."
    echo ""
    echo "Posibles causas:"
    echo "1. TU IP NO ESTA PERMITIDA: Hostinger bloquea IPs desconocidas."
    echo "   Solucion: Ve al panel de Hostinger -> Remote MySQL -> Agrega tu IP actual."
    echo "2. Credenciales incorrectas en .env"
    echo ""
    rm -f temp_sync.sql
    exit 1
fi

echo "✅ Datos descargados correctamente."

echo ""
echo "⏳ Paso 2/2: Importando a Docker Local..."

# 2. Importar
docker compose exec -T mysql mysql -u $DB_USERNAME -p$DB_PASSWORD $DB_DATABASE < temp_sync.sql

if [ $? -ne 0 ]; then
    echo "❌ Error al importar los datos."
else
    echo "✅ ¡Sincronizacion completada exitosamente!"
fi

# Limpiar
rm -f temp_sync.sql
