FROM php:8.4-fpm-alpine
RUN apk add --no-cache curl fcgi git icu-dev libpq-dev libxml2-dev linux-headers su-exec unzip imagemagick imagemagick-jpeg imagemagick-webp libheif \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS imagemagick-dev libheif-dev \
 && docker-php-ext-install dom exif intl pcntl pdo_pgsql \
 && pecl install redis xdebug imagick && docker-php-ext-enable redis xdebug imagick \
 && curl -fsSL https://raw.githubusercontent.com/renatomefi/php-fpm-healthcheck/master/php-fpm-healthcheck -o /usr/local/bin/php-fpm-healthcheck \
 && chmod +x /usr/local/bin/php-fpm-healthcheck \
 && apk del .build-deps
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/zz-healthcheck.conf /usr/local/etc/php-fpm.d/zz-healthcheck.conf
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/php/entrypoint.sh /usr/local/bin/lodge-portal-entrypoint
RUN chmod +x /usr/local/bin/lodge-portal-entrypoint
WORKDIR /var/www/html
ENTRYPOINT ["lodge-portal-entrypoint"]
CMD ["php-fpm"]
