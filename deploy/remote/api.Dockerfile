FROM php:8.5-cli-alpine

RUN apk add --no-cache \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev \
        unzip \
    && docker-php-ext-install \
        bcmath \
        intl \
        mbstring \
        pcntl \
        pdo_pgsql \
        zip \
    && addgroup -g 1000 app \
    && adduser -D -u 1000 -G app app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY api/composer.json api/composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts

COPY --chown=app:app api/ .
RUN rm -f bootstrap/cache/*.php \
    && composer dump-autoload --no-dev --optimize \
    && mkdir -p storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R app:app storage bootstrap/cache

USER app

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
