FROM php:8.1-apache

# Install system dependencies and PHP extensions for MongoDB and MySQL
RUN apt-get update && apt-get install -y libssl-dev git unzip \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && a2enmod rewrite

# Install Composer globally
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy project files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Automatically install dependencies if vendor folder is missing from Git
RUN if [ ! -d "vendor" ]; then COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --ignore-platform-reqs; fi

# Optimize PHP session settings
RUN echo "session.gc_maxlifetime = 1440" > /usr/local/etc/php/conf.d/session.ini \
    && echo "output_buffering = On" >> /usr/local/etc/php/conf.d/session.ini
