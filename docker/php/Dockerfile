FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev zip vim mysql-common iputils-ping\
    && docker-php-ext-install pdo pdo_mysql zip 

RUN a2enmod rewrite
RUN a2enmod proxy
RUN a2enmod proxy_http

COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer