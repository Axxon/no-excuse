FROM php:8.5-cli-alpine

ARG USER_ID=1000
ARG GROUP_ID=1000

RUN apk add --no-cache \
        icu-libs \
        libpq \
        libzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        pcntl \
        pdo_pgsql \
        zip \
    && apk del .build-deps \
    && addgroup -g "${GROUP_ID}" app \
    && adduser -D -u "${USER_ID}" -G app app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
USER app

EXPOSE 8000

CMD ["sh", "-lc", "cd public && exec php -S 0.0.0.0:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"]
