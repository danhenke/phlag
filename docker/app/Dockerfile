# syntax=docker/dockerfile:1.7

FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl git unzip libpq-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

EXPOSE 80

CMD ["php", "-S", "0.0.0.0:80", "-t", "public", "public/index.php"]
