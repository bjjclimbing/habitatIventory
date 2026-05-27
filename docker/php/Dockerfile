FROM php:8.2-apache

# =========================
# BASE
# =========================
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev zip vim mariadb-client iputils-ping \
    libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql zip gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite
RUN a2enmod proxy
RUN a2enmod proxy_http

# =========================
# ARGS
# =========================
ARG APACHE_ENV
ARG APP_ENV=prod

# =========================
# Apache config
# =========================
COPY docker/apache/vhost.${APACHE_ENV} /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www

# =========================
# PROD → copiar código
# =========================
COPY . .

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN if [ "$APP_ENV" = "prod" ]; then \
    composer install --no-dev --optimize-autoloader; \
    fi