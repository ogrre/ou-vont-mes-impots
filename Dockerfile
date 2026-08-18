FROM php:8.4-fpm-alpine AS runtime

RUN apk add --no-cache \
        curl \
        libxml2 \
        libzip \
        nginx \
        postgresql-libs \
        supervisor \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libxml2-dev \
        libzip-dev \
        postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" bcmath dom opcache pdo_pgsql xmlreader zip \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/* /tmp/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-progress --prefer-dist

COPY . .
COPY docker/production/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/production/php.ini /usr/local/etc/php/conf.d/production.ini
COPY docker/production/supervisord.conf /etc/supervisord.conf
COPY docker/production/entrypoint.sh /usr/local/bin/production-entrypoint

RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction \
    && mkdir -p /run/nginx storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/production-entrypoint

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    PORT=8080

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl --fail --silent http://127.0.0.1:8080/up > /dev/null || exit 1

ENTRYPOINT ["production-entrypoint"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
