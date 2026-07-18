FROM dunglas/frankenphp:1-php8.5-alpine

RUN install-php-extensions bcmath intl pcntl pdo_pgsql zip \
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
    && mkdir -p /config /data \
    && chown -R app:app storage bootstrap/cache /config /data

COPY deploy/remote/Caddyfile /etc/caddy/Caddyfile

ENV XDG_CONFIG_HOME=/config XDG_DATA_HOME=/data

USER app

EXPOSE 8000

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
