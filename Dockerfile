FROM php:8.2-cli

# Instalar dependencias del sistema necesarias
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql zip gd

WORKDIR /app

# Instalar Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer

# Exponer puerto de Laravel
EXPOSE 8000

# Comando para servir Laravel en desarrollo
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
