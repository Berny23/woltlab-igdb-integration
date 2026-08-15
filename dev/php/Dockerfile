FROM docker.io/library/php:8.3-fpm

# Required extensions not already in the base image:
# gmp, intl, exif, pdo_mysql + GD with WebP/JPEG/FreeType support.
# zip/unzip for development
RUN apt-get update && apt-get install -y --no-install-recommends \
        libgmp-dev \
        libicu-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libwebp-dev \
        libfreetype6-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" gmp intl exif pdo_mysql gd zip opcache \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html