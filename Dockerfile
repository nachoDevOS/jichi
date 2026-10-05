# syntax=docker/dockerfile:1
#
# Jichi en un contenedor: nginx + php-fpm (serversideup/php), PHP 8.3.
# Tres etapas: dependencias de PHP, assets de Vite y la imagen final.
# Detalle y decisiones en docs/DOCKER-TECNICO.md.

ARG PHP_VERSION=8.3

# --- Base: PHP con las extensiones que el sistema usa ---
FROM serversideup/php:${PHP_VERSION}-fpm-nginx AS base

USER root
# gd con freetype/jpeg/webp: QR, foto del carnet y texto girado de la guía.
# intl, bcmath: Laravel. pdo_pgsql y zip ya vienen.
RUN install-php-extensions gd intl bcmath
USER www-data

# --- Dependencias de PHP (sin las de desarrollo) ---
FROM base AS vendor

COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# --- Assets de Vite ---
FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY . .
# app.css hace @source sobre las vistas de paginación de vendor/.
COPY --from=vendor /var/www/html/vendor ./vendor
RUN npm run build

# --- Imagen final ---
FROM base AS final

COPY --chown=www-data:www-data --from=vendor /var/www/html/vendor ./vendor
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

# storage/ no viaja en la imagen (.dockerignore): se arma vacía.
# storage/fonts es la caché de fuentes de DomPDF, y tiene que poder escribirse.
RUN mkdir -p \
        storage/app/private storage/app/public \
        storage/framework/cache/data storage/framework/sessions storage/framework/views \
        storage/logs storage/fonts bootstrap/cache \
    && composer dump-autoload --optimize --no-dev --no-interaction
