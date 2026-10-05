FROM php:8.4-fpm-alpine

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql zip gd intl bcmath opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader --no-progress

COPY . .
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && mkdir -p storage/app/private/imports storage/framework/cache storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

EXPOSE 9000
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
