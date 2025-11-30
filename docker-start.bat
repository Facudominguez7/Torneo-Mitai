@echo off
echo 🐘 Iniciando Torneos Mita'í con Docker...
echo.

REM Verificar Docker
docker --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ Docker no esta instalado.
    pause
    exit /b 1
)

REM Copiar .env
if not exist .env (
    echo 📝 Copiando .env.docker a .env...
    copy .env.docker .env >nul
)

REM Iniciar contenedores
echo 🚀 Iniciando contenedores...
docker-compose up -d --build

REM Esperar MySQL
echo ⏳ Esperando a que MySQL este listo (15s)...
timeout /t 15 /nobreak >nul

REM Preguntar por sincronizacion de BD
echo.
set /p SYNC_DB="¿Quieres sincronizar la base de datos desde Produccion? (S/N): "
if /i "%SYNC_DB%"=="S" (
    call sync-db.bat
)
echo.

REM Comandos Laravel
echo ⚙️  Configurando Laravel...
docker-compose exec -T app php artisan migrate --force
docker-compose exec -T app php artisan key:generate --show
docker-compose exec -T app php artisan storage:link
docker-compose exec -T app php artisan config:clear

echo.
echo ✅ Aplicacion iniciada correctamente!
echo    Web:  http://localhost:8000
echo    Vite: http://localhost:5173
echo.
pause
