FROM composer:2 AS composer
FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev libcurl4-openssl-dev unzip \
    && docker-php-ext-install pdo_mysql mbstring curl \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
COPY *.php *.css *.js *.html ./
COPY api ./api
COPY pages ./pages
COPY img ./img
COPY docker/apache.conf /etc/apache2/conf-enabled/malife.conf
RUN a2enmod setenvif && mkdir -p uploads /var/lib/malife \
    && chown www-data:www-data uploads /var/lib/malife \
    && ln -s /var/lib/malife/webauthn_challenges.json webauthn_challenges.json
