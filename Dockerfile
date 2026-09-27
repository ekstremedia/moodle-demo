FROM composer:2 AS composer
FROM php:8.4-apache
ARG MOODLE_VERSION=5.2.3
ARG MOODLE_SHA256=067633d65a1dc196354c3fddbcedee40a329673279416350a8d4f74898fda8a1
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl libfreetype6-dev libicu-dev libjpeg62-turbo-dev libpng-dev \
    libpq-dev libxml2-dev libzip-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" exif gd intl opcache pdo_pgsql pgsql soap zip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
RUN curl -fsSL "https://github.com/moodle/moodle/archive/refs/tags/v${MOODLE_VERSION}.tar.gz" -o /tmp/moodle.tar.gz \
    && echo "${MOODLE_SHA256}  /tmp/moodle.tar.gz" | sha256sum -c - \
    && mkdir -p /var/www/html \
    && tar -xzf /tmp/moodle.tar.gz -C /var/www/html --strip-components=1 \
    && rm /tmp/moodle.tar.gz \
    && cd /var/www/html \
    && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p /var/moodledata \
    && chown -R www-data:www-data /var/moodledata
COPY plugins/local/ /var/www/html/public/local/
COPY docker/config.php /var/www/html/config.php
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/moodle.ini
COPY docker/moodle-cron /usr/local/bin/moodle-cron
COPY docker/seed-user.php /opt/moodle/seed-user.php
RUN chmod +x /usr/local/bin/moodle-cron && a2enmod rewrite headers
WORKDIR /var/www/html
