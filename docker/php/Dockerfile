FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    $PHPIZE_DEPS \
    linux-headers \
    librdkafka-dev \
    git \
    unzip \
    curl \
    supervisor

RUN pecl install redis rdkafka \
    && docker-php-ext-enable redis rdkafka

RUN docker-php-ext-install opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY docker/php/conf.d/opcache.ini $PHP_INI_DIR/conf.d/opcache.ini
COPY docker/php/conf.d/app.ini $PHP_INI_DIR/conf.d/app.ini

# Kopiuj entrypoint i nadaj uprawnienia
COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

WORKDIR /var/www/html

RUN addgroup -g 1000 appgroup && adduser -u 1000 -G appgroup -s /bin/sh -D appuser
RUN chown -R appuser:appgroup /var/www

# Domyślnie uruchom php-fpm (dla serwisów bez konsumera)
# Dla serwisów z konsumerem nadpisz CMD w docker-compose
USER appuser

EXPOSE 9000

CMD ["php-fpm"]
