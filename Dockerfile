FROM php:8.3-fpm-alpine

# System dependencies
RUN apk add --no-cache \
    bash \
    curl \
    git \
    icu-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    libzip-dev \
    oniguruma-dev \
    postgresql-dev \
    unzip \
    nodejs \
    npm

# PHP extensions
RUN docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pdo_pgsql \
        pgsql \
        pcntl \
        zip

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --optimize-autoloader --no-interaction \
    && npm ci \
    && npm run build \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
