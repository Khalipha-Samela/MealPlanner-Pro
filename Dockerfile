FROM php:8.2-apache

# Install PostgreSQL extension
RUN docker-php-ext-install pdo pdo_pgsql

# Copy project files
COPY . /var/www/html/

# Enable Apache rewrite
RUN a2enmod rewrite

EXPOSE 80