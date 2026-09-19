#!/bin/bash
#
# Sincroniza la base de datos remota (Hostinger) hacia el MySQL local de Docker.
#
# - La base remota SOLO se lee (mysqldump con reintentos y timeout).
# - Antes de tocar la base local se guarda un backup completo en disco.
# - Si el import final falla, la base local se restaura automaticamente del backup.
# - Nunca modifica la base remota y no ejecuta migraciones de Laravel.
# - Las credenciales viajan solo por variables de entorno (MYSQL_PWD), nunca por argv.
#
# Uso: ./sync-db.sh [--yes]

set -euo pipefail

# --------------------------------------------------------------- Constantes
readonly DUMP_TIMEOUT_SECS=600       # Timeout por intento de mysqldump remoto.
readonly DUMP_MAX_ATTEMPTS=3         # Intentos totales del dump remoto.
readonly DUMP_MIN_BYTES=1024         # Tamano minimo sano del dump validado.
readonly MYSQL_WAIT_TIMEOUT_SECS=60  # Espera maxima de salud del mysql local.
readonly PROGRESO_INTERVALO_SECS=5   # Cadencia del indicador de avance del dump.
readonly IDENT_RE='^[A-Za-z0-9_]+$' # Esquemas: solo letras, numeros y guion bajo (un '-' inicial seria inyeccion de opciones).
readonly PORT_RE='^[0-9]+$'

# --------------------------------------------------------------- Estado
ASSUME_YES=0
DUMP_FILE=""
ERR_FILE=""
BACKUP_FILE=""

# --------------------------------------------------------------- Utilidades
fail() {
    echo "❌ $*" >&2
    exit 1
}

# Lee .env sin ejecutarlo como shell: interpreta pares KEY=VALUE de forma
# literal y exporta unicamente las variables de la lista blanca. Los valores
# pueden contener metacaracteres; se respetan comillas envolventes.
cargar_entorno_dotenv() {
    local archivo="$1"
    local linea clave valor primero ultimo
    local clave_permitida es_permitida
    local -a permitidas=(
        REMOTE_DB_HOST REMOTE_DB_PORT REMOTE_DB_DATABASE REMOTE_DB_USERNAME REMOTE_DB_PASSWORD
        DB_DATABASE DB_USERNAME DB_PASSWORD
    )
    while IFS= read -r linea || [[ -n "$linea" ]]; do
        linea="${linea%"${linea##*[![:space:]]}"}"
        linea="${linea#"${linea%%[![:space:]]*}"}"
        linea="${linea%$'\r'}"
        if [[ -z "$linea" || "$linea" == "#"* || "$linea" != *=* ]]; then
            continue
        fi
        clave="${linea%%=*}"
        valor="${linea#*=}"
        if ! [[ "$clave" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]]; then
            continue
        fi
        es_permitida=0
        for clave_permitida in "${permitidas[@]}"; do
            if [[ "$clave" == "$clave_permitida" ]]; then
                es_permitida=1
                break
            fi
        done
        if (( ! es_permitida )); then
            continue
        fi
        if ((${#valor} >= 2)); then
            primero="${valor:0:1}"
            ultimo="${valor: -1}"
            if [[ "$primero" == "$ultimo" && ( "$primero" == '"' || "$primero" == "'" ) ]]; then
                valor="${valor:1:${#valor}-2}"
            fi
        fi
        printf -v "$clave" '%s' "$valor"
        export "$clave"
    done < "$archivo"
}

# Limpieza garantizada en EXIT/INT/TERM: borra los archivos temporales.
cleanup() {
    local rc=$?
    trap - EXIT INT TERM
    if [[ -n "$DUMP_FILE" && -f "$DUMP_FILE" ]]; then
        rm -f "$DUMP_FILE"
    fi
    if [[ -n "$ERR_FILE" && -f "$ERR_FILE" ]]; then
        rm -f "$ERR_FILE"
    fi
    if [[ -n "$BACKUP_FILE" && -f "$BACKUP_FILE" ]]; then
        rm -f "$BACKUP_FILE"
    fi
    exit "$rc"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

# Cliente mysql local como root (MYSQL_ROOT_PASSWORD = DB_PASSWORD en compose):
# la contraseña via MYSQL_PWD por entorno del contenedor, nunca con -p.
run_local_mysql() {
    docker compose exec -T -e MYSQL_PWD mysql mysql -u root "$@"
}

# Sin esta variante, 'docker compose exec -T' hereda el stdin del script, se
# queda con la terminal y la corrida parece colgada.
run_local_mysql_sin_stdin() {
    run_local_mysql "$@" </dev/null
}

show_help() {
    cat <<'AYUDA'
Uso: ./sync-db.sh [--yes]

Sincroniza la base de datos remota hacia el MySQL local de Docker.

Opciones:
  --yes       Confirma la operacion destructiva sin preguntar (modo no interactivo).
  -h, --help  Muestra esta ayuda y sale.

Sin --yes se pide confirmacion interactiva; la respuesta por defecto es No.
En modo no interactivo (sin terminal) el script aborta: usa --yes.
Antes de reemplazar la base local se guarda un backup completo en disco y,
si el import final falla, se restaura automaticamente. No ejecuta migraciones
de Laravel y nunca modifica la base remota (solo lectura).
AYUDA
}

validate_identificador() {
    local valor="$1" variable="$2"
    if ! [[ "$valor" =~ $IDENT_RE ]]; then
        fail "$variable contiene un valor no seguro para SQL; se rechazo (solo letras, numeros y guion bajo)."
    fi
    if (( ${#valor} > 64 )); then
        fail "$variable supera el limite de 64 caracteres de MySQL."
    fi
}

# --------------------------------------------------------------- Argumentos
if (($# > 1)); then
    echo "❌ Se acepta como maximo un argumento: --yes." >&2
    exit 2
fi
for arg in "$@"; do
    case "$arg" in
        --yes)
            ASSUME_YES=1
            ;;
        -h | --help)
            show_help
            exit 0
            ;;
        *)
            echo "❌ Argumento desconocido: $arg" >&2
            echo "Uso: ./sync-db.sh [--yes] (o --help)" >&2
            exit 2
            ;;
    esac
done

# --------------------------------------------------------------- Raiz del repo
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$REPO_ROOT"

# --------------------------------------------------------------- Lock anti-solapamiento
LOCK_DIR="${XDG_RUNTIME_DIR:-/tmp}"
LOCK_FILE="$LOCK_DIR/sync-db-torneo-mitai.lock"
command -v flock >/dev/null 2>&1 || fail "flock no esta disponible (paquete util-linux)."
: >"$LOCK_FILE" 2>/dev/null || fail "No se puede crear el lock en $LOCK_DIR."
exec 200>"$LOCK_FILE"
if ! flock -n 200; then
    fail "Ya hay una sincronizacion en curso (lock: $LOCK_FILE)."
fi

# --------------------------------------------------------------- Entorno (.env)
if [[ ! -f .env ]]; then
    fail "No se encontro el archivo .env en $REPO_ROOT."
fi
    cargar_entorno_dotenv .env

for VAR in REMOTE_DB_HOST REMOTE_DB_DATABASE REMOTE_DB_USERNAME REMOTE_DB_PASSWORD DB_DATABASE DB_USERNAME DB_PASSWORD; do
    if [[ -z "${!VAR:-}" ]]; then
        fail "Falta la variable obligatoria $VAR en .env."
    fi
done

# Validar el puerto con regex ANTES de la conversion aritmetica: bajo set -u
# un valor invalido debe caer en el mensaje controlado, no en un error de (( )).
# El tope de 5 digitos evita ademas overflow aritmetico en la conversion.
REMOTE_DB_PORT="${REMOTE_DB_PORT:-3306}"
if ! [[ "$REMOTE_DB_PORT" =~ $PORT_RE ]] || ((${#REMOTE_DB_PORT} > 5)); then
    fail "REMOTE_DB_PORT debe ser un numero de puerto valido (1-65535)."
fi
REMOTE_DB_PORT=$((10#$REMOTE_DB_PORT))
if ((REMOTE_DB_PORT < 1 || REMOTE_DB_PORT > 65535)); then
    fail "REMOTE_DB_PORT debe ser un numero de puerto valido (1-65535)."
fi

validate_identificador "$REMOTE_DB_DATABASE" "REMOTE_DB_DATABASE"
validate_identificador "$DB_DATABASE" "DB_DATABASE"
case "$DB_DATABASE" in
    mysql | information_schema | performance_schema | sys)
        fail "DB_DATABASE no puede ser un esquema del sistema de MySQL (mysql, information_schema, performance_schema, sys)."
        ;;
esac

command -v docker >/dev/null 2>&1 || fail "Docker no esta instalado o no esta en el PATH."
docker compose version >/dev/null 2>&1 || fail "El plugin 'docker compose' no esta disponible."
command -v timeout >/dev/null 2>&1 || fail "timeout (coreutils) no esta disponible."

# --------------------------------------------------------------- Confirmacion
echo "🔄 Sincronizacion de Base de Datos (Remota -> Docker Local)"
echo "============================================================"
echo "📡 Host remoto : $REMOTE_DB_HOST:$REMOTE_DB_PORT"
echo "📂 Base remota : $REMOTE_DB_DATABASE (solo lectura)"
echo "🏠 Base local  : $DB_DATABASE (se SOBREESCRIBIRA)"
echo ""
if ((ASSUME_YES)); then
    echo "⚠️  Modo no interactivo: confirmacion destructiva asumida (--yes)."
else
    if [[ ! -t 0 ]]; then
        fail "No hay terminal para confirmar y no se paso --yes; se aborta sin tocar nada."
    fi
    echo "⚠️  ADVERTENCIA: esto SOBREESCRIBIRA la base local '$DB_DATABASE'."
    echo "    Se guardara un backup local restaurable antes de reemplazarla."
    RESPUESTA=""
    read -r -p "¿Estas seguro de continuar? [s/N]: " RESPUESTA || true
    if [[ ! "$RESPUESTA" =~ ^[sS]$ ]]; then
        echo "❌ Cancelado."
        exit 0
    fi
fi

# --------------------------------------------------------------- Paso 1/4: mysql local
echo ""
echo "⏳ Paso 1/4: Iniciando el servicio mysql de Docker..."
docker compose up -d mysql
echo "   Esperando salud de mysql local (max ${MYSQL_WAIT_TIMEOUT_SECS}s)..."
ESPERA=0
until docker compose exec -T mysql mysqladmin ping -h localhost --silent </dev/null >/dev/null 2>&1; do
    sleep 2
    ESPERA=$((ESPERA + 2))
    if ((ESPERA >= MYSQL_WAIT_TIMEOUT_SECS)); then
        fail "mysql local no respondio dentro de ${MYSQL_WAIT_TIMEOUT_SECS}s."
    fi
done
echo "✅ mysql local listo."

# --------------------------------------------------------------- Paso 2/5: dump remoto
DUMP_FILE="$(mktemp /tmp/sync-db-dump.XXXXXX)"
ERR_FILE="$(mktemp /tmp/sync-db-dump.err.XXXXXX)"
chmod 600 "$DUMP_FILE" "$ERR_FILE"

echo ""
echo "⏳ Paso 2/4: Descargando dump remoto (solo lectura)..."
echo "   El servidor remoto tarda ~30s y no emite avance propio; se muestra el progreso local."
export MYSQL_PWD="$REMOTE_DB_PASSWORD"
DUMP_OK=0
for INTENTO in $(seq 1 "$DUMP_MAX_ATTEMPTS"); do
    echo "   Intento $INTENTO/$DUMP_MAX_ATTEMPTS..."
    : >"$DUMP_FILE"
    # Formas largas con '=': el valor queda adjunto y nunca se interpreta como opcion.
    timeout "$DUMP_TIMEOUT_SECS" docker compose exec -T -e MYSQL_PWD mysql mysqldump \
        --host="$REMOTE_DB_HOST" \
        --port="$REMOTE_DB_PORT" \
        --user="$REMOTE_DB_USERNAME" \
        --single-transaction --quick --lock-tables=false --no-tablespaces \
        --column-statistics=0 --set-gtid-purged=OFF --routines --triggers --events \
        "$REMOTE_DB_DATABASE" </dev/null >"$DUMP_FILE" 2>"$ERR_FILE" &
    DUMP_PID=$!
    SEGUNDOS_INICIO=$SECONDS
    while kill -0 "$DUMP_PID" 2>/dev/null; do
        sleep "$PROGRESO_INTERVALO_SECS"
        printf '\r      descargando... %ss transcurridos, %s bytes recibidos ' \
            "$((SECONDS - SEGUNDOS_INICIO))" "$(wc -c <"$DUMP_FILE")"
    done
    printf '\r%*s\r' 70 ''
    if wait "$DUMP_PID"; then
        DUMP_OK=1
        break
    fi
    echo "   ⚠️  El intento $INTENTO fallo:"
    tail -n 5 "$ERR_FILE" || true
    if ((INTENTO < DUMP_MAX_ATTEMPTS)); then
        sleep $((INTENTO * 10))
    fi
done
if (( ! DUMP_OK )); then
    echo ""
    echo "❌ ERROR CRITICO: no se pudo descargar la base de datos remota."
    echo "Posibles causas:"
    echo "1. Tu IP no esta permitida: Hostinger -> Remote MySQL -> agrega tu IP actual."
    echo "2. Credenciales incorrectas en .env."
    exit 1
fi

# Validacion del dump ANTES de tocar la base local.
DUMP_BYTES="$(wc -c <"$DUMP_FILE")"
if ((DUMP_BYTES < DUMP_MIN_BYTES)); then
    fail "El dump descargado es demasiado chico ($DUMP_BYTES bytes); se aborta."
fi
if ! grep -q 'CREATE TABLE' "$DUMP_FILE"; then
    fail "El dump no contiene CREATE TABLE; se aborta sin tocar la base local."
fi
if ! tail -n 25 "$DUMP_FILE" | grep -q -- '-- Dump completed'; then
    fail "El dump no tiene marca de finalizacion; se aborta sin tocar la base local."
fi
echo "✅ Dump remoto descargado y validado ($DUMP_BYTES bytes)."

# --------------------------------------------------------------- Paso 3/4: backup local
export MYSQL_PWD="$DB_PASSWORD"
BACKUP_FILE="$(mktemp /tmp/sync-db-backup.XXXXXX)"
chmod 600 "$BACKUP_FILE"
echo ""
echo "⏳ Paso 3/4: Respaldando la base local '$DB_DATABASE' antes de reemplazarla..."
EXISTE_LOCAL="$(run_local_mysql_sin_stdin -N -B -e "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = '$DB_DATABASE'")" ||
    fail "No se pudo consultar el mysql local."
if ((EXISTE_LOCAL == 0)); then
    echo "ℹ️  '$DB_DATABASE' todavia no existe; no hay nada que respaldar."
    rm -f "$BACKUP_FILE"
    BACKUP_FILE=""
elif ! docker compose exec -T -e MYSQL_PWD mysql mysqldump -u root \
    --single-transaction --quick --no-tablespaces --routines --triggers --events \
    --databases "$DB_DATABASE" </dev/null >"$BACKUP_FILE" 2>"$ERR_FILE"; then
    tail -n 5 "$ERR_FILE" >&2 || true
    fail "No se pudo respaldar '$DB_DATABASE'; se aborta sin tocar la base local."
else
    echo "✅ Backup local listo ($(wc -c <"$BACKUP_FILE") bytes)."
fi

# --------------------------------------------------------------- Paso 4/4: reemplazo con rollback
echo ""
echo "⏳ Paso 4/4: Reemplazando '$DB_DATABASE' con el dump remoto..."
if ! run_local_mysql_sin_stdin -e "DROP DATABASE IF EXISTS \`$DB_DATABASE\`; CREATE DATABASE \`$DB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"; then
    fail "Fallo el drop/create de '$DB_DATABASE'."
fi
if ! run_local_mysql "$DB_DATABASE" <"$DUMP_FILE"; then
    if [[ -z "$BACKUP_FILE" ]]; then
        fail "La importacion fallo y no habia backup previo que restaurar."
    fi
    echo "❌ La importacion fallo; restaurando '$DB_DATABASE' desde el backup local..." >&2
    if run_local_mysql <"$BACKUP_FILE"; then
        echo "✅ Base local restaurada al estado previo." >&2
    else
        echo "❌ La restauracion tambien fallo. Backup conservado en: $BACKUP_FILE" >&2
        BACKUP_FILE=""
    fi
    exit 1
fi
TABLAS_FINAL="$(run_local_mysql_sin_stdin -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DB_DATABASE'")" ||
    fail "No se pudo verificar la base local tras el reemplazo."
if ((TABLAS_FINAL < 1)); then
    fail "'$DB_DATABASE' quedo sin tablas tras el reemplazo."
fi

echo ""
echo "✅ ¡Sincronizacion completada exitosamente!"
echo "   '$DB_DATABASE' tiene ahora $TABLAS_FINAL tablas."
echo "   No se ejecutaron migraciones de Laravel y la base remota nunca fue modificada."
