FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    && docker-php-ext-install mysqli pdo pdo_mysql

RUN a2dismod mpm_event mpm_worker 2>/dev/null; \
    a2enmod mpm_prefork rewrite

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
