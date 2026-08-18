FROM php:8.3-fpm

WORKDIR /var/www/laravel

# deb.debian.org is unreachable from the office network, so apt goes through a
# mirror. Override at build time when a direct connection is available:
#   docker compose build --build-arg DEBIAN_MIRROR=https://deb.debian.org php
ARG DEBIAN_MIRROR=https://mirror.yandex.ru
RUN sed -i "s|https\?://deb.debian.org|${DEBIAN_MIRROR}|g" /etc/apt/sources.list.d/debian.sources

# GD (with JPEG/WebP/FreeType) powers PhotoConverter — without it every upload
# silently falls back to storing the original file instead of a WebP.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd pcntl pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

