FROM php:8.3-apache
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/download/2.7.24/install-php-extensions /usr/local/bin/
RUN install-php-extensions gd pdo_mysql zip intl bcmath exif opcache @composer \
 && a2enmod rewrite \
 && sed -ri 's!/var/www/html!/var/www/concrete/public!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
 && printf "memory_limit=512M\nupload_max_filesize=32M\npost_max_size=32M\n" > /usr/local/etc/php/conf.d/concrete.ini
WORKDIR /var/www/concrete
