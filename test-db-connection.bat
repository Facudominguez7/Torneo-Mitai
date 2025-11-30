@echo off
setlocal EnableDelayedExpansion

echo 🔍 Probando conexion a Base de Datos Remota...
echo ============================================

REM Leer variables del archivo .env
if not exist .env (
    echo ❌ No se encontro el archivo .env
    exit /b 1
)

for /f "tokens=1* delims==" %%a in ('type .env ^| findstr "REMOTE_DB_"') do (
    set "%%a=%%b"
)

if "%REMOTE_DB_HOST%"=="" (
    echo ❌ Error: REMOTE_DB_HOST no configurado.
    exit /b 1
)

echo 📡 Host: %REMOTE_DB_HOST%
echo 👤 Usuario: %REMOTE_DB_USERNAME%
echo 📂 Base de datos: %REMOTE_DB_DATABASE%
echo.

echo Intentando conectar...
docker-compose exec -T mysql mysql -h %REMOTE_DB_HOST% -P %REMOTE_DB_PORT% -u %REMOTE_DB_USERNAME% -p%REMOTE_DB_PASSWORD% -e "SELECT '✅ CONEXION EXITOSA! Versión: ' as status, @@version;" %REMOTE_DB_DATABASE%

if %errorlevel% neq 0 (
    echo.
    echo ❌ FALLO LA CONEXION.
    echo.
    echo Diagnostico:
    echo 1. Si dice "Access denied for user...":
    echo    - Tu usuario o contraseña estan mal.
    echo    - O tu IP no esta en la lista blanca (aunque ya la agregaste).
    echo.
    echo 2. Si dice "Can't connect to MySQL server...":
    echo    - El host esta mal escrito.
    echo    - O el puerto 3306 esta bloqueado por un firewall.
) else (
    echo.
    echo ✅ Las credenciales funcionan correctamente.
)

echo.
pause
