FROM php:8.3-fpm-alpine3.19

RUN apk --update add --no-cache freetype-dev \
    libpng libpng-dev libjpeg-turbo-dev \
    imagemagick-dev \
    libzip-dev zip \
    curl-dev \
    oniguruma-dev

RUN apk add --no-cache --virtual \
    .phpize-deps $PHPIZE_DEPS linux-headers \
    && pecl install redis-6.0.2 && docker-php-ext-enable redis \
    && docker-php-ext-configure zip \
    && docker-php-ext-configure gd \
            --enable-gd \
            --with-freetype=/usr/include/ \
            --with-jpeg=/usr/include/ \
    && docker-php-ext-install curl zip mbstring pdo_mysql gd sockets \
    && apk del .phpize-deps

RUN curl -s -o /usr/local/bin/composer https://getcomposer.org/composer.phar \
    && chmod 0755 /usr/local/bin/composer

RUN curl -LsS https://codeception.com/codecept.phar -o /usr/local/bin/codecept \
    && chmod 0755 /usr/local/bin/codecept

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

WORKDIR /code
