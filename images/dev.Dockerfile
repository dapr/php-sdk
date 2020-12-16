FROM php:8.0-apache

RUN a2enmod rewrite
RUN pecl install xdebug && docker-php-ext-enable xdebug
COPY images/xdebug.ini /tmp/xdebug.ini
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
RUN cat /tmp/xdebug.ini >> $PHP_INI_DIR/php.ini
