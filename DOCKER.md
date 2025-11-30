# 🐳 Docker para Torneos Mita'í

Esta aplicación Laravel ha sido dockerizada para permitir su ejecución en cualquier computadora con Docker instalado.

## 📋 Requisitos

- **Docker Desktop** instalado ([Descargar aquí](https://www.docker.com/products/docker-desktop))
- Al menos 4GB de RAM disponible
- Windows 10/11, macOS o Linux

## 🚀 Inicio Rápido

### En Windows (PowerShell)

```powershell
.\docker-start.ps1
```

### En Linux/Mac (Bash)

```bash
chmod +x docker-start.sh
./docker-start.sh
```

### Manualmente

```bash
# 1. Copiar archivo de entorno
cp .env.docker .env

# 2. Iniciar contenedores
docker-compose up -d

# 3. Ejecutar migraciones
docker-compose exec app php artisan migrate

# 4. Crear enlace de storage
docker-compose exec app php artisan storage:link
```

## 🌐 Acceso a la Aplicación

Una vez iniciada, la aplicación estará disponible en:

- **Backend Laravel**: http://localhost:8000
- **Vite Dev Server**: http://localhost:5173
- **Base de datos MySQL**: localhost:3306

### Acceso desde otros dispositivos en la red

Para acceder desde otros dispositivos (móviles, tablets, etc.) en tu misma red local:

1. Ejecuta el script de inicio que te mostrará tu IP local
2. Accede desde otros dispositivos usando: `http://TU_IP_LOCAL:8000`

Ejemplo: `http://192.168.0.16:8000`

## 📦 Servicios Docker

La aplicación utiliza los siguientes contenedores:

- **nginx**: Servidor web (puerto 8000)
- **app**: PHP 8.3 con PHP-FPM
- **mysql**: Base de datos MySQL 8.0 (puerto 3306)
- **vite**: Node.js para desarrollo frontend con hot-reload (puerto 5173)

## 🛠️ Comandos Útiles

### Gestión de Contenedores

```bash
# Ver estado de contenedores
docker-compose ps

# Ver logs en tiempo real
docker-compose logs -f

# Ver logs de un servicio específico
docker-compose logs -f app
docker-compose logs -f nginx

# Detener contenedores (sin eliminar datos)
docker-compose stop

# Iniciar contenedores detenidos
docker-compose start

# Reiniciar contenedores
docker-compose restart

# Detener y eliminar contenedores (conserva volúmenes)
docker-compose down

# Detener y eliminar TODO (incluye base de datos)
docker-compose down -v
```

### Comandos de Laravel

```bash
# Ejecutar comandos artisan
docker-compose exec app php artisan [comando]

# Ejemplos:
docker-compose exec app php artisan migrate
docker-compose exec app php artisan db:seed
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan route:list

# Acceder al contenedor
docker-compose exec app sh

# Ejecutar Composer
docker-compose exec app composer install
docker-compose exec app composer update
```

### Comandos de NPM

```bash
# Ejecutar comandos npm
docker-compose exec vite npm [comando]

# Ejemplos:
docker-compose exec vite npm install
docker-compose exec vite npm run build

# Reiniciar Vite si hay problemas
docker-compose restart vite
```

### Base de Datos

```bash
# Acceder a MySQL (pedirá contraseña interactivamente)
docker-compose exec mysql mysql -u root -p

# Backup de la base de datos
docker-compose exec mysql mysqldump -u root -p${DB_PASSWORD} ${DB_DATABASE} > backup.sql

# Restaurar backup
docker-compose exec -T mysql mysql -u root -p${DB_PASSWORD} ${DB_DATABASE} < backup.sql

# Ver logs de MySQL
docker-compose logs -f mysql
```

## 🔧 Configuración

### Variables de Entorno

La configuración principal está en el archivo `.env`. Un archivo de ejemplo `.env.docker` se proporciona con valores predeterminados para Docker.

**Valores importantes:**

- `DB_HOST=mysql` - El nombre del servicio Docker de la base de datos
- `DB_DATABASE=mitai` - Nombre de la base de datos
- `DB_USERNAME=root` - Usuario de MySQL
- `DB_PASSWORD=secret` - Contraseña de MySQL (cámbiala en producción)

### Puertos

Si los puertos predeterminados están en uso, puedes cambiarlos en `docker-compose.yml`:

```yaml
services:
  nginx:
    ports:
      - "8000:80"  # Cambiar 8000 por otro puerto
  
  vite:
    ports:
      - "5173:5173"  # Cambiar 5173 por otro puerto
  
  mysql:
    ports:
      - "3306:3306"  # Cambiar 3306 por otro puerto
```

## 🔒 Seguridad

### Desarrollo Local

La configuración actual está optimizada para desarrollo local con las siguientes características de seguridad:

- Nginx configurado con headers de seguridad básicos
- Límite de tamaño de upload (100MB)
- Protección contra acceso a archivos sensibles (.env, composer.json, etc.)
- Usuario no privilegiado en el contenedor PHP

### Producción

**⚠️ IMPORTANTE**: Esta configuración NO debe usarse directamente en producción. Para producción debes:

1. **Cambiar credenciales**:
   - Generar nueva `APP_KEY`: `php artisan key:generate`
   - Usar contraseñas fuertes para MySQL
   - Configurar `APP_ENV=production` y `APP_DEBUG=false`

2. **HTTPS**:
   - Configurar certificados SSL/TLS
   - Usar un proxy inverso (Nginx, Traefik, Caddy)

3. **Firewall**:
   - Exponer solo los puertos necesarios
   - Usar firewall para limitar acceso

4. **Optimizaciones**:
   ```bash
   docker-compose exec app php artisan config:cache
   docker-compose exec app php artisan route:cache
   docker-compose exec app php artisan view:cache
   docker-compose exec vite npm run build
   ```

5. **Backups automáticos**:
   - Configurar backups regulares de la base de datos
   - Respaldar el volumen de MySQL

6. **Logs y Monitoreo**:
   - Configurar rotación de logs
   - Implementar monitoreo de contenedores

## 🐛 Solución de Problemas

### El puerto está en uso

```bash
# Ver qué proceso usa el puerto
netstat -ano | findstr :8000  # Windows
lsof -i :8000                 # Linux/Mac

# Cambiar el puerto en docker-compose.yml
```

### Vite no recarga automáticamente

```bash
# Reiniciar el contenedor de Vite
docker-compose restart vite

# O forzar reconstrucción
docker-compose up -d --build vite
```

### Error de permisos en storage

```bash
# Desde el contenedor
docker-compose exec app chmod -R 775 storage bootstrap/cache
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
```

### MySQL no inicia

```bash
# Ver logs
docker-compose logs mysql

# Eliminar volumen y recrear (⚠️ PIERDE TODOS LOS DATOS)
docker-compose down -v
docker-compose up -d
```

### Limpiar todo y empezar de nuevo

```bash
# Detener y eliminar todo
docker-compose down -v

# Eliminar imágenes (opcional)
docker-compose down --rmi all -v

# Iniciar de nuevo
docker-compose up -d --build
```

## 📝 Notas Adicionales

- Los cambios en archivos PHP se reflejan automáticamente (sin reiniciar)
- Los cambios en archivos JS/CSS se recargan automáticamente con Vite
- Los datos de MySQL persisten entre reinicios gracias a volúmenes Docker
- El directorio `node_modules` se maneja dentro del contenedor para mejor rendimiento

## 🤝 Diferencias con Laragon

| Aspecto | Laragon | Docker |
|---------|---------|--------|
| IP del servidor | 192.168.0.16:8000 | localhost:8000 |
| Acceso en red | Manual con --host | Automático |
| Base de datos | Local MySQL | Contenedor MySQL |
| Node/NPM | Local | Contenedor Node |
| Portabilidad | Solo Windows | Windows/Mac/Linux |
| Aislamiento | Global | Por proyecto |

## 📚 Recursos

- [Documentación de Docker](https://docs.docker.com/)
- [Docker Compose](https://docs.docker.com/compose/)
- [Laravel Deployment](https://laravel.com/docs/deployment)

---

¿Problemas? Revisa la sección de solución de problemas o consulta los logs con `docker-compose logs -f`
