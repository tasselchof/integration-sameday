FROM php:8.0-fpm

LABEL maintainer="oawms.com" \
    org.label-schema.docker.dockerfile="/Dockerfile" \
    org.label-schema.name="Orderadmin"

## Update package information
RUN apt-get update

## Install Composer
RUN curl -sS https://getcomposer.org/installer \
  | php -- --install-dir=/usr/local/bin --filename=composer

###
## PHP Extensisons
###

## Install zip libraries and extension
RUN apt-get install --yes git zlib1g-dev libzip-dev \
    && docker-php-ext-install zip

## Install intl library and extension
RUN apt-get install --yes libicu-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl

###
## Optional PHP extensions
###

RUN apt-get install -y sudo libfreetype6-dev libjpeg62-turbo-dev libpng-dev zip && \
    apt-get install -y libc-client-dev libkrb5-dev libcurl3-gnutls libcurl4-openssl-dev libpcre3-dev libxml2 libxml2-dev && \
    apt-get install -y git-core libpng-dev icu-devtools libmcrypt4 libmcrypt-dev libpq-dev libtidy-dev && \
    apt-get install -y libgearman-dev libmemcached-dev redis

RUN sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'

RUN docker-php-ext-install pcntl zip pdo_pgsql exif bcmath soap && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install gd && \
    pecl install gearman && \
    docker-php-ext-enable gearman

## mbstring for i18n string support
# RUN docker-php-ext-install mbstring

###
## Some laminas/laminas-db supported PDO extensions
###

## MySQL PDO support
# RUN docker-php-ext-install pdo_mysql

## PostgreSQL PDO support
# RUN apt-get install --yes libpq-dev \
#     && docker-php-ext-install pdo_pgsql

###
## laminas/laminas-cache supported extensions
###

## APCU
# RUN pecl install apcu \
#     && docker-php-ext-enable apcu

## Memcached
 RUN apt-get install --yes libmemcached-dev \
     && pecl install memcached \
     && docker-php-ext-enable memcached

## MongoDB
# RUN pecl install mongodb \
#     && docker-php-ext-enable mongodb

## Redis support.  igbinary and libzstd-dev are only needed based on
## redis pecl options
# RUN pecl install igbinary \
#     && docker-php-ext-enable igbinary \
#     && apt-get install --yes libzstd-dev \
#     && pecl install redis \
#     && docker-php-ext-enable redis


WORKDIR /var/www