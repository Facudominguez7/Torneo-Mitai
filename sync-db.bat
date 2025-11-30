@echo off
setlocal EnableDelayedExpansion

echo 🔄 Sincronizacion de Base de Datos (Hostinger -> Docker Local)
echo ============================================================
echo.

REM Leer variables del archivo .env
if not exist .env (
    echo ❌ No se encontro el archivo .env
    exit /b 1
)

REM Leer variables de base de datos remota
for /f "tokens=1* delims==" %%a in ('type .env ^| findstr "REMOTE_DB_"') do (
    set "%%a=%%b"
)

REM Leer variables de base de datos local
for /f "tokens=1* delims==" %%a in ('type .env ^| findstr /R "^DB_USERNAME= ^DB_PASSWORD= ^DB_DATABASE="') do (
    set "%%a=%%b"
)

REM Verificar configuración
if "%REMOTE_DB_HOST%"=="" (
    echo ❌ Error: REMOTE_DB_HOST no configurado.
    exit /b 1
)

echo 📡 Conectando a: %REMOTE_DB_HOST%
echo 📂 Base de datos: %REMOTE_DB_DATABASE%
echo.
echo ⚠️  ADVERTENCIA: Esto SOBREESCRIBIRA tu base de datos local 'mitai'.
set /p CONFIRM="¿Estas seguro de continuar? (S/N): "

if /i not "%CONFIRM%"=="S" (
    echo ❌ Cancelado.
    exit /b 0
)

echo.
echo ⏳ Paso 1/2: Descargando datos de Hostinger...
echo    (Esto puede tardar dependiendo de tu conexion)

REM 1. Descargar dump a un archivo temporal local
docker-compose exec -T mysql mysqldump -h %REMOTE_DB_HOST% -P %REMOTE_DB_PORT% -u %REMOTE_DB_USERNAME% -p%REMOTE_DB_PASSWORD% %REMOTE_DB_DATABASE% --no-tablespaces --column-statistics=0 --single-transaction --quick --lock-tables=false > temp_sync.sql

REM Verificar si la descarga falló
if %errorlevel% neq 0 (
    echo.
    echo ❌ ERROR CRITICO: No se pudo descargar la base de datos.
    echo.
    echo Posibles causas:
    echo 1. TU IP NO ESTA PERMITIDA: El error "Access denied" significa que Hostinger bloqueo tu IP.
    echo    Solucion: Ve al panel de Hostinger -^> Remote MySQL -^> Agrega tu IP actual.
    echo 2. Credenciales incorrectas en .env
    echo.
    if exist temp_sync.sql del temp_sync.sql
    pause
    exit /b 1
)

echo ✅ Datos descargados correctamente.

echo.
echo ⏳ Paso 2/2: Importando a Docker Local...

REM 2. Importar el archivo descargado usando variables de entorno
docker-compose exec -T mysql mysql -u %DB_USERNAME% -p%DB_PASSWORD% %DB_DATABASE% < temp_sync.sql

if %errorlevel% neq 0 (
    echo ❌ Error al importar los datos.
) else (
    echo ✅ ¡Sincronizacion completada exitosamente!
)

REM Limpiar archivo temporal
if exist temp_sync.sql del temp_sync.sql

echo.
pause
