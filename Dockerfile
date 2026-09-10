FROM php:8.1-apache

# Install system dependencies and PHP extensions for MongoDB and MySQL
RUN apt-get update && apt-get install -y libssl-dev git unzip \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && docker-php-ext-install mysqli pdo pdo_mysql

# Install Composer globally
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy project files first
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Run composer install safely
RUN if [ -f composer.json ]; then COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --ignore-platform-reqs; fi
