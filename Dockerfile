FROM php:8.2-apache

# Install system dependencies and PHP extensions for MongoDB
RUN apt-get update && apt-get install -y libssl-dev git unzip \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && docker-php-ext-install mysqli pdo pdo_mysql

# Install Composer globally
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy project files first so composer.json is available
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Run composer install with extra permissions
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader
