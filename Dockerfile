# Install PHP libraries
FROM composer:2.0 as composer

WORKDIR /usr/local/src/

COPY composer.lock /usr/local/src/
COPY composer.json /usr/local/src/

RUN composer install --ignore-platform-reqs --optimize-autoloader \
    --no-plugins --no-scripts --prefer-dist

# Prepare generic compiler
FROM php:8.3.3-cli-alpine3.19 as compile

ENV PHP_SWOOLE_VERSION="v5.1.2" \
  PHP_MONGODB_VERSION="1.16.1"

RUN \
  apk add --no-cache --virtual .deps \
  linux-headers \
  make \
  automake \
  autoconf \
  gcc \
  g++ \
  git \
  zlib-dev \
  openssl-dev \
  yaml-dev \
  imagemagick \
  imagemagick-dev \
  libjpeg-turbo-dev \
  jpeg-dev \
  libjxl-dev \
  libmaxminddb-dev \
  zstd-dev \
  brotli-dev \
  lz4-dev \
  curl-dev

  
RUN docker-php-ext-install sockets

# Compile Swoole
FROM compile AS swoole

RUN \
  git clone --depth 1 --branch $PHP_SWOOLE_VERSION https://github.com/swoole/swoole-src.git && \
  cd swoole-src && \
  phpize && \
  ./configure --enable-sockets --enable-http2 --enable-openssl && \
  make && make install && \
  cd ..

# Mongodb Extension
FROM compile as mongodb
RUN \
  git clone --depth 1 --branch $PHP_MONGODB_VERSION https://github.com/mongodb/mongo-php-driver.git && \
  cd mongo-php-driver && \
  git submodule update --init && \
  phpize && \
  ./configure && \
  make && make install

# Proxy
FROM php:8.3.3-cli-alpine3.19 as final

ARG UTOPIA_DATA_API_VERSION
ENV UTOPIA_DATA_API_VERSION=$UTOPIA_DATA_API_VERSION

LABEL maintainer="team@appwrite.io"

RUN \
  apk update \
  && apk add --no-cache --virtual .deps \
  make \
  automake \
  autoconf \
  gcc \
  g++ \
  curl-dev \
  && apk add --no-cache \
  libstdc++ \
  postgresql-dev \
  && docker-php-ext-install sockets pdo_mysql pdo_pgsql \
  && apk del .deps \
  && rm -rf /var/cache/apk/*

WORKDIR /usr/local/

# Source code
COPY ./app /usr/local/app

# Extensions and libraries
COPY --from=composer /usr/local/src/vendor /usr/local/vendor
COPY --from=swoole /usr/local/lib/php/extensions/no-debug-non-zts-20230831/swoole.so /usr/local/lib/php/extensions/no-debug-non-zts-20230831/
COPY --from=mongodb /usr/local/lib/php/extensions/no-debug-non-zts-20230831/mongodb.so /usr/local/lib/php/extensions/no-debug-non-zts-20230831/

RUN echo extension=swoole.so >> /usr/local/etc/php/conf.d/swoole.ini

EXPOSE 80

CMD [ "php", "app/http.php" ]