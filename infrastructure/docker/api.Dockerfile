FROM php:8.4-cli-alpine

RUN apk add --no-cache bash git curl unzip curl-dev icu-dev libxml2-dev libzip-dev oniguruma-dev mysql-client linux-headers \
    && docker-php-ext-install bcmath curl dom intl mbstring pcntl pdo_mysql xml xmlwriter zip \
    && rm -rf /tmp/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

# Copy the complete Laravel application before Composer runs because Laravel's
# post-autoload scripts execute artisan package discovery.
COPY apps/api ./
RUN composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R ug+rw storage bootstrap/cache

COPY infrastructure/docker/api-entrypoint.sh /usr/local/bin/api-entrypoint
RUN chmod +x /usr/local/bin/api-entrypoint

EXPOSE 8000
ENTRYPOINT ["/usr/local/bin/api-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
