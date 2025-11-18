# Dockerfile
FROM php:8.2-apache

# Install required PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy project files into the container
COPY . /var/www/html/

# Enable Apache mod_rewrite (if you use pretty URLs)
RUN a2enmod rewrite

# Expose Apache port
EXPOSE 80
