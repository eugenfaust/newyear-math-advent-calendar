FROM composer:2 AS composer

FROM php:8.3-cli-alpine

RUN apk add --no-cache postgresql-libs postgresql-dev unzip libpng libjpeg-turbo libwebp freetype \
    && apk add --no-cache --virtual .gd-build libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install pdo_pgsql gd \
    && apk del postgresql-dev .gd-build \
    && printf 'date.timezone=UTC\n' > /usr/local/etc/php/conf.d/timezone.ini

COPY --from=composer /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache git
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-source --no-progress --no-scripts --no-dev
COPY . .
COPY app.ini /usr/local/etc/php/conf.d/app.ini
RUN mkdir -p /uploads var/cache var/log \
    && ln -s /uploads public/uploads

EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]

