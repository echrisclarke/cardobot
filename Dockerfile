# Railway / local: build context is repo root
FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev zlib1g-dev \
        libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd mbstring pdo pdo_mysql mysqli opcache \
    && rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf \
    && a2enmod mpm_prefork rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache-ports.conf /etc/apache2/ports.conf.template
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf.template
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/html
COPY public /var/www/html/public
COPY database /var/www/html/database

RUN mkdir -p /data/uploads /var/www/html/public/uploads /var/www/html/public/user-cards \
    && chown -R www-data:www-data /data /var/www/html/public/uploads /var/www/html/public/user-cards

ENV UPLOAD_ROOT=/data/uploads
EXPOSE 8080
ENTRYPOINT ["/entrypoint.sh"]
