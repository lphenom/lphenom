FROM php:8.1-cli-alpine

RUN apk add --no-cache \
        bash \
        git \
        unzip \
        $PHPIZE_DEPS \
        linux-headers \
    && docker-php-ext-install pdo pdo_mysql pcntl sockets \
    && pecl install redis-6.0.2 \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS linux-headers

COPY --from=composer:2.7.9 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json /app/composer.json

RUN composer install --no-progress --prefer-dist --optimize-autoloader --no-interaction 2>/dev/null || true

COPY . /app

RUN composer install --no-progress --prefer-dist --optimize-autoloader --no-interaction

CMD ["php", "bin/lphenom"]


