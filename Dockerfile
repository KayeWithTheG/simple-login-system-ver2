FROM php:8.1-apache

# Install system dependencies and PHP extensions for MongoDB and MySQL
RUN apt-get update && apt-get install -y libssl-dev git unzip \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && docker-php-ext-install mysqli pdo pdo_mysql

# Copy all project files including the existing vendor folder
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html
