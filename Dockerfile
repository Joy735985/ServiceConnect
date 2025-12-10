FROM php:8.2-apache

# Enable mysqli + pdo_mysql
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy project into apache root
COPY . /var/www/html/

# Apache rewrite (optional)
RUN a2enmod rewrite

# Permission fix (optional)
RUN chown -R www-data:www-data /var/www/html
