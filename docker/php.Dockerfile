FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libwebp-dev libavif-dev libfreetype6-dev default-mysql-client \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-avif --with-freetype \
    && docker-php-ext-install -j"$(nproc)" intl zip gd exif bcmath pdo_mysql pcntl opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN echo "memory_limit=1G\nupload_max_filesize=64M\npost_max_size=64M" > /usr/local/etc/php/conf.d/vju.ini

WORKDIR /var/www/html
