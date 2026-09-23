FROM php:8.3-apache

ENV APP_ENV=production

# PDO/mysqli for the app itself, plus the mysql CLI so the entrypoint can
# import database/schema.sql on first boot (no separate migration step to run).
# GD is for compressing/resizing product photos on upload.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev default-mysql-client \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql mysqli gd \
    && docker-php-ext-enable opcache \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php-production.ini /usr/local/etc/php/conf.d/zz-production.ini

RUN a2enmod rewrite headers deflate expires \
    && rm -f /etc/apache2/mods-enabled/mpm_event.conf /etc/apache2/mods-enabled/mpm_event.load \
             /etc/apache2/mods-enabled/mpm_worker.conf /etc/apache2/mods-enabled/mpm_worker.load \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load

COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/deflate.conf /etc/apache2/conf-available/deflate.conf
RUN a2enconf deflate

COPY . /var/www/html/

# Product images are uploaded at runtime — make sure Apache can write there.
RUN mkdir -p /var/www/html/uploads/products \
    && chown -R www-data:www-data /var/www/html/uploads

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8080
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
