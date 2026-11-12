# syntax=docker/dockerfile:1

# ---- Base: PHP 8.5 FPM with the extensions Celestia needs ------------------
# pdo_mysql -> MySQL (runtime DB), bcmath/pcntl -> Laravel + queue worker
# zip is needed by Composer. pdo_sqlite is already bundled for `artisan test`.
# opcache is built into PHP 8.5 itself, so it must NOT be installed here
# (docker-php-ext-install opcache fails on 8.5).
FROM php:8.5-fpm-alpine AS base

RUN apk add --no-cache libzip-dev icu-libs tzdata \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath pcntl zip

ENV TZ=Asia/Manila
WORKDIR /var/www/html

# ---- Vendor: install Composer dependencies ---------------------------------
FROM base AS vendor

ARG INSTALL_DEV=false
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN if [ "$INSTALL_DEV" = "true" ]; then \
        composer install --no-interaction --no-progress --no-scripts \
            --prefer-dist --optimize-autoloader; \
    else \
        composer install --no-dev --no-interaction --no-progress --no-scripts \
            --prefer-dist --optimize-autoloader; \
    fi

# ---- Production image -------------------------------------------------------
FROM base AS production

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /var/www/html/vendor ./vendor

# Ensure Laravel's writable directories exist and remove any stale cached
# manifests before running package discovery for --no-dev dependencies.
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && rm -f bootstrap/cache/*.php \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
