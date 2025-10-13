# syntax=docker/dockerfile:1
# check=error=true
ARG BUILDKIT_SBOM_SCAN_CONTEXT=true
ARG BUILDKIT_SBOM_SCAN_STAGE=true
ARG SOURCE_DATE_EPOCH

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --classmap-authoritative

FROM php:8.4-cli AS base

ARG SOURCE_DATE_EPOCH

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl git unzip libpq-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

WORKDIR /app

FROM base AS builder

ARG SOURCE_DATE_EPOCH

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=vendor /app/vendor ./vendor
COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative \
    && php phlag app:build phlag

FROM base AS runtime

ARG SOURCE_DATE_EPOCH

LABEL org.opencontainers.image.description="Phlag feature flag and remote configuration service"

ENV PHLAG_PHAR=/app/phlag

COPY public ./public
COPY --from=builder /app/builds/phlag ./phlag

RUN chmod +x /app/phlag \
    && ln -sf /app/phlag /usr/local/bin/phlag

EXPOSE 80

CMD ["/usr/local/bin/php", "-S", "0.0.0.0:80", "-t", "public", "public/index.php"]
