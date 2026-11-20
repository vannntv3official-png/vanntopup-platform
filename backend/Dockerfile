FROM php:8.3-cli-alpine AS base

RUN apk add --no-cache \
    curl \
    git \
    icu-dev \
    libpq-dev \
    libzip-dev \
    oniguruma-dev \
    sqlite-dev \
    unzip \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
        pdo_sqlite \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts

COPY . .
RUN composer dump-autoload --optimize \
    && sed -i 's/\r$//' /var/www/html/docker/entrypoint.sh \
    && chmod +x /var/www/html/docker/entrypoint.sh \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

ENV PHP_CLI_SERVER_WORKERS=4

EXPOSE 8000

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
