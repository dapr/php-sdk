FROM dunglas/frankenphp:latest AS base
ENV VERSION=1
RUN apt-get update && apt-get install -y wget git unzip && apt-get clean && rm -rf /var/cache/apt/lists
RUN install-php-extensions curl intl zip sodium opcache apcu @composer

FROM base AS vendor
COPY composer.json composer.json
COPY composer.lock composer.lock
RUN composer install --no-dev -o -n

FROM base AS config
ARG SERVICE
ENV SERVICE=$SERVICE
COPY services/$SERVICE public/services/$SERVICE
COPY --from=vendor /app/vendor public/vendor
COPY index.php public/index.php
COPY global-config.php public/global-config.php

FROM config AS production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
ENV PHP_CLI_SERVER_WORKERS=100
COPY images/opcache.ini /tmp/opcache.ini
RUN cat /tmp/opcache.ini >> $PHP_INI_DIR/php.ini

FROM production AS development
ARG SERVICE
COPY images/xdebug.ini /tmp/xdebug.ini
ENV DBGP_IDEKEY=$SERVICE
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" && \
    cd $(php -d 'display_errors=stderr' -r 'echo ini_get("extension_dir");') && \
    mv /php-disabled/xdebug.so . && mv /php-disabled/docker-php-ext-xdebug.ini $PHP_INI_DIR/conf.d/docker-php-ext-xdebug.ini
RUN cat /tmp/xdebug.ini >> $PHP_INI_DIR/php.ini
COPY images/opcache.ini /tmp/opcache.ini
RUN cat /tmp/opcache.ini >> $PHP_INI_DIR/php.ini
