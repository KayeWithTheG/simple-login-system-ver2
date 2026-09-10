FROM php:8.2-apache
COPY . /var/www/html/
RUN apt-get update && apt-get install -y libssl-dev && pecl install mongodb && docker-php-ext-enable mongodb
RUN docker-php-ext-install mysqli pdo pdo_mysql