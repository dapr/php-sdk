# syntax=docker/dockerfile:labs
# withinboredom/php-base-min == docker build --pull -t withinboredom/php-base-min --target base -f images/tests.Dockerfile .
FROM php:8.0-fpm AS base
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN apt-get update && apt-get install -y wget git unzip && apt-get clean
RUN install-php-extensions curl zip grpc protobuf && mkdir -p /tests
WORKDIR /tests

FROM withinboredom/php-base-min AS vendor
ARG BASE
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY ${BASE}composer.json composer.json
#COPY ${BASE}composer.lock composer.lock
COPY . /php-sdk
RUN composer install --no-dev -o -n

FROM withinboredom/php-base-min AS config
ARG BASE
COPY ${BASE} /tests
COPY --from=vendor /tests/vendor vendor

FROM config AS production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
