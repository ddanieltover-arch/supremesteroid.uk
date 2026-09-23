# Generic production image. FrankenPHP serves Laravel from /app/public.
# Nginx + PHP-FPM is documented in docs/DEPLOYMENT_STANDARD_SERVER.md and docker/nginx.conf.
# Symfony 8.1 in composer.lock requires PHP >= 8.4.1.

FROM node:22-alpine AS frontend-builder

WORKDIR /app

COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN corepack enable \
  && corepack prepare pnpm@12.4.2 --activate \
  && pnpm install --frozen-lockfile

COPY . .
RUN corepack enable && corepack prepare pnpm@12.4.2 --activate && pnpm run build

FROM dunglas/frankenphp:1-php8.4-alpine AS runner

RUN apk add --no-cache \
    curl \
    git \
    unzip \
    postgresql-client \
    && install-php-extensions \
    pdo_pgsql \
    pgsql \
    intl \
    zip \
    bcmath \
    opcache \
    pcntl

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /app

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . .
COPY --from=frontend-builder /app/public/build ./public/build
COPY Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint.sh

RUN chmod +x /usr/local/bin/app-entrypoint.sh \
    && composer dump-autoload --optimize --no-dev --no-interaction \
    && rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php \
    && mkdir -p \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/cache \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

ENV PORT=8080

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/app-entrypoint.sh"]
