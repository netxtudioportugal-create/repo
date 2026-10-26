# Portable image for any container cloud (Cloud Run, Fly.io, Render, ECS, Azure Container Apps).
# Laravel Cloud does not need this file; it builds from the repository directly.

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql opcache zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!Listen 80!Listen ${PORT}!g' /etc/apache2/ports.conf \
    && sed -ri -e 's!<VirtualHost \*:80>!<VirtualHost *:${PORT}>!g' /etc/apache2/sites-available/000-default.conf
ENV PORT=8080

COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
COPY --from=vendor /app /var/www/html
RUN chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint
EXPOSE 8080
ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]
