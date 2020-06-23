FROM php:7.4-cli

LABEL version="1.0.0"
LABEL description="Postuf SocksProxyAsync Library"

RUN apt-get update && apt-get install -y git curl gnupg unzip libzip-dev \
&& docker-php-ext-install zip \
&& docker-php-ext-install sockets
RUN pecl install xdebug \
&& docker-php-ext-enable xdebug
RUN curl -sL https://deb.nodesource.com/setup_14.x  | bash -
RUN apt-get -y install nodejs

ENV COMPOSER_ALLOW_SUPERUSER 1
RUN curl -sS https://getcomposer.org/installer | php \
&& mv composer.phar /usr/local/bin/composer

COPY src /app/src/
COPY tests /app/tests/
COPY node /app/node/
COPY composer.json /app/
COPY composer.lock /app/

WORKDIR /app

RUN composer update
RUN cd node && npm install