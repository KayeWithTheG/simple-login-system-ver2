FROM php:8.1-apache

# Install system dependencies and PHP extensions for MongoDB and MySQL
RUN apt-get update && apt-get install -y libssl-dev git unzip \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && a2enmod rewrite

# Optimize PHP session and execution handling for cloud deployment
RUN echo "session.gc_maxlifetime = 1440" > /usr/local/etc/php/conf.d/session.ini \
    && echo "output_buffering = On" >> /usr/local/etc/php/conf.d/session.ini

# Copy all project files including your vendor folder
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html
