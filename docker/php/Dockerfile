FROM php:8.5-fpm-alpine

RUN apk add --no-cache \
    bash \
    curl \
    git \
    unzip \
    icu-dev \
    libzip-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    postgresql-dev \
    linux-headers \
    $PHPIZE_DEPS \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        bcmath \
        exif \
        gd \
        intl \
        pcntl \
        sockets \
        zip \
    && pecl install redis \
    && pecl install xdebug \
    && docker-php-ext-enable redis \
    && docker-php-ext-enable xdebug \
    && { \
        echo "xdebug.mode=develop,debug,coverage"; \
        echo "xdebug.start_with_request=trigger"; \
        echo "xdebug.discover_client_host=1"; \
        echo "xdebug.client_port=9003"; \
        echo "xdebug.log_level=0"; \
      } > /usr/local/etc/php/conf.d/docker-php-ext-xdebug-settings.ini \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

CMD ["php-fpm"]
