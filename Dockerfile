FROM node:24-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.ts ./
RUN npm run build

FROM composer:latest AS backend
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --optimize

FROM php:8.4-fpm-alpine

RUN apk add --no-cache nginx supervisor sqlite \
    && apk add --no-cache --virtual .build-deps sqlite-dev \
    && docker-php-ext-install pdo pdo_sqlite bcmath \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/* /tmp/*

WORKDIR /var/www/html

COPY --from=backend /app/vendor ./vendor
COPY --from=backend /app/public ./public
COPY --from=backend /app/bootstrap ./bootstrap
COPY --from=backend /app/config ./config
COPY --from=backend /app/routes ./routes
COPY --from=backend /app/storage ./storage
COPY --from=backend /app/resources ./resources
COPY --from=backend /app/app ./app
COPY --from=backend /app/artisan ./artisan
COPY --from=backend /app/database ./database
COPY --from=backend /app/composer.json ./composer.json

COPY --from=frontend /app/public/build ./public/build

RUN chown -R www-data:www-data /var/www/html \
    && mkdir -p /data && chown -R www-data:www-data /data

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

VOLUME /data
EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
