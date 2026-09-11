FROM php:8.1-apache

# Install system dependencies and PHP extensions for MongoDB and MySQL
RUN apt-get update && apt-get install -y libssl-dev git unzip \
    && pecl install mongodb-1.19.3 \
    && docker-php-ext-enable mongodb \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && a2enmod rewrite

# Install Composer globally
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy project files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Force clear old vendor/lock and install a compatible MongoDB library version
RUN rm -rf vendor composer.lock \
    && COMPOSER_ALLOW_SUPERUSER=1 composer require mongodb/mongodb:^1.20 --no-interaction --ignore-platform-reqs

# Optimize PHP session settings
RUN echo "session.gc_maxlifetime = 1440" > /usr/local/etc/php/conf.d/session.ini \
    && echo "output_buffering = On" >> /usr/local/etc/php/conf.d/session.ini
