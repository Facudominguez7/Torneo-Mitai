# Dockerfile para Laravel con PHP 8.3
FROM php:8.3-fpm-alpine AS base

# Instalar dependencias del sistema
RUN apk add --no-cache \
    bash \
    curl \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    oniguruma-dev \
    mysql-client \
    nodejs \
    npm

# Instalar extensiones PHP requeridas por Laravel
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    zip \
    exif \
    pcntl \
    bcmath \
    gd

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar directorio de trabajo
WORKDIR /var/www/html

# Copiar archivos de composer
COPY composer.json composer.lock ./

# Instalar dependencias de composer (sin autoload por ahora)
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copiar el resto de la aplicación
COPY . .

# Generar autoload optimizado
RUN composer dump-autoload --optimize

# Establecer permisos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Exponer puerto
EXPOSE 9000

# Usuario no privilegiado
USER www-data

CMD ["php-fpm"]
