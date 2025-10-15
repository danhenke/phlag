# syntax=docker/dockerfile:1
# check=error=true
ARG TARGETPLATFORM

FROM composer:2 AS vendor
WORKDIR /app
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --classmap-authoritative

FROM php:8.4-cli AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl unzip libcap2-bin libpq-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

WORKDIR /app

FROM base AS builder

COPY --from=vendor /usr/bin/composer /usr/bin/composer
COPY --from=vendor /app/vendor ./vendor
COPY . .

RUN composer dump-autoload \
    --no-dev \
    --strict-psr \
    --strict-ambiguous \
    --no-ansi \
    --optimize \
    --classmap-authoritative \
    && php phlag app:build phlag

FROM base AS runtime

RUN useradd --system --create-home phlag \
    && chown -R phlag:phlag /app
ENV PHLAG_PHAR=/app/phlag

COPY --from=builder /app/public ./public
COPY --from=builder /app/bootstrap ./bootstrap
COPY --from=builder /app/config ./config
COPY --from=builder /app/routes ./routes
COPY --from=builder /app/app ./app
COPY --from=builder /app/vendor ./vendor
COPY --from=builder /app/database ./database
COPY --from=builder /app/builds/phlag ./phlag

RUN chmod +x /app/phlag \
    && ln -sf /app/phlag /usr/local/bin/phlag \
    && chown -R phlag:phlag /app \
    && chown -h phlag:phlag /usr/local/bin/phlag \
    && setcap 'cap_net_bind_service=+ep' /usr/local/bin/php

WORKDIR /app

USER phlag

EXPOSE 80

CMD ["/usr/local/bin/php", "-S", "0.0.0.0:80", "-t", "/app/public", "/app/public/index.php"]
