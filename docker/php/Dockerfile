FROM dunglas/frankenphp:1-php8.4-bookworm

RUN install-php-extensions \
        pdo_sqlite \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/Caddyfile /etc/caddy/Caddyfile
COPY docker/php/start.sh /usr/local/bin/start-app

RUN chmod +x /usr/local/bin/start-app

WORKDIR /app

ENTRYPOINT ["/usr/local/bin/start-app"]
