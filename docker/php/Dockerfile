FROM php:8.4-fpm-alpine
RUN apk add --no-cache curl fcgi git icu-dev libpq-dev linux-headers unzip \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
 && docker-php-ext-install intl pcntl pdo_pgsql \
 && pecl install redis xdebug && docker-php-ext-enable redis xdebug \
 && curl -fsSL https://raw.githubusercontent.com/renatomefi/php-fpm-healthcheck/master/php-fpm-healthcheck -o /usr/local/bin/php-fpm-healthcheck \
 && chmod +x /usr/local/bin/php-fpm-healthcheck \
 && apk del .build-deps
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/zz-healthcheck.conf /usr/local/etc/php-fpm.d/zz-healthcheck.conf
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini
WORKDIR /var/www/html
