# Production image: nginx + php-fpm in one container is avoided; this builds the PHP-FPM app image.
# Run it behind nginx (docker/nginx.conf) with separate worker (queue:work) and scheduler (schedule:work) processes.

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources resources
COPY vite.config.ts tsconfig.json ./
COPY public public
RUN npm run build

FROM php:8.3-fpm AS app
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libwebp-dev libavif-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-avif --with-freetype \
    && docker-php-ext-install -j"$(nproc)" intl zip gd exif bcmath pdo_mysql pcntl opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN printf "opcache.enable=1\nopcache.validate_timestamps=0\nmemory_limit=512M\nupload_max_filesize=64M\npost_max_size=64M\nexpose_php=Off\n" > /usr/local/etc/php/conf.d/production.ini

WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
COPY --from=assets /app/public/build public/build
COPY --from=assets /app/bootstrap/ssr bootstrap/ssr
RUN composer dump-autoload --optimize --no-dev \
    && php artisan filament:assets \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
CMD ["php-fpm"]
