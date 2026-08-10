# ---------------------------------------------------------------------------
# Stage 1 — build the front-end bundle
#
# Node is only needed to produce CSS and JS. Keeping it in a separate stage means
# the runtime image ships neither Node nor node_modules, which removes roughly
# 300 MB and a large amount of attack surface from what actually runs in
# production.
# ---------------------------------------------------------------------------
FROM node:22-slim AS assets

WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources
RUN npm run build


# ---------------------------------------------------------------------------
# Stage 2 — PHP dependencies
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --prefer-dist \
        --optimize-autoloader


# ---------------------------------------------------------------------------
# Stage 3 — runtime
# ---------------------------------------------------------------------------
FROM php:8.4-apache

ARG APACHE_DOCUMENT_ROOT=/var/www/html/public
ENV APACHE_DOCUMENT_ROOT=${APACHE_DOCUMENT_ROOT}

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql mbstring bcmath zip opcache \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/*

# Compiled PHP is cached in memory rather than re-parsed on every request.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.memory_consumption=192'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Upload limits are kept in step with the application's own limit so a rejected
# file produces the translated error instead of an untranslated PHP warning.
RUN { \
        echo 'upload_max_filesize=12M'; \
        echo 'post_max_size=14M'; \
        echo 'memory_limit=256M'; \
        echo 'expose_php=Off'; \
    } > /usr/local/etc/php/conf.d/app.ini

RUN a2enmod rewrite headers \
    && sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" \
        /etc/apache2/sites-available/*.conf \
        /etc/apache2/apache2.conf \
        /etc/apache2/conf-available/*.conf \
    && printf '<Directory %s>\n  AllowOverride All\n  Require all granted\n</Directory>\nServerTokens Prod\nServerSignature Off\n' "${APACHE_DOCUMENT_ROOT}" \
        > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel

WORKDIR /var/www/html

COPY --from=vendor /build/vendor ./vendor
COPY . .
COPY --from=assets /build/public/build ./public/build

RUN mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x docker/entrypoint.sh

EXPOSE 80

HEALTHCHECK --interval=20s --timeout=5s --start-period=40s --retries=5 \
    CMD php -r "exit(@file_get_contents('http://127.0.0.1/up') === false ? 1 : 0);"

ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["apache2-foreground"]
