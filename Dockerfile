FROM php:8.3-apache

# PDO/mysqli for the app itself, plus the mysql CLI so the entrypoint can
# import database/schema.sql on first boot (no separate migration step to run).
RUN docker-php-ext-install pdo pdo_mysql mysqli \
    && apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite \
    && rm -f /etc/apache2/mods-enabled/mpm_event.conf /etc/apache2/mods-enabled/mpm_event.load \
             /etc/apache2/mods-enabled/mpm_worker.conf /etc/apache2/mods-enabled/mpm_worker.load \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load

COPY . /var/www/html/

# Product images are uploaded at runtime — make sure Apache can write there.
RUN mkdir -p /var/www/html/uploads/products \
    && chown -R www-data:www-data /var/www/html/uploads

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8080
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
