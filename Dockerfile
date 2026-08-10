FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY routes ./routes
RUN npm run build

FROM php:8.4-fpm-alpine
ARG VCS_REF=unknown
LABEL org.opencontainers.image.source="https://github.com/indraraka/lomba-diskominfo" \
      org.opencontainers.image.revision="${VCS_REF}" \
      org.opencontainers.image.licenses="MIT"
RUN apk add --no-cache nginx supervisor icu-dev libzip-dev oniguruma-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo_mysql pcntl intl zip bcmath opcache mbstring \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS
WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/lomba-entrypoint
RUN php artisan package:discover --ansi
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs storage/app/private public/uploads/branding bootstrap/cache /run/nginx \
    && chown -R www-data:www-data storage bootstrap/cache public/uploads/branding \
    && chmod +x /usr/local/bin/lomba-entrypoint
EXPOSE 80
HEALTHCHECK --interval=15s --timeout=5s --start-period=30s --retries=5 \
    CMD wget -qO- http://127.0.0.1/up >/dev/null || exit 1
ENTRYPOINT ["/usr/local/bin/lomba-entrypoint"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
