FROM php:8.5-fpm-alpine

RUN apk add --no-cache \
        bash \
        curl \
        git \
        icu-dev \
        libpng-dev \
        libpq-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-client \
        sqlite-dev \
        unzip \
    && docker-php-ext-install \
        bcmath \
        intl \
        mbstring \
        pdo_pgsql \
        pdo_sqlite \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-liga.ini
COPY docker/php/entrypoint.sh /usr/local/bin/liga-entrypoint

RUN chmod +x /usr/local/bin/liga-entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["liga-entrypoint"]
CMD ["php-fpm"]
