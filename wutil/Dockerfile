FROM php:8.3-apache

# The instructions for rewrites
RUN a2enmod rewrite \
    && a2enmod ssl \
    && a2enmod headers \
    && a2enmod deflate \
    && a2enmod filter \
    && a2enmod proxy \
    && a2enmod proxy_http \
    && a2enmod proxy_fcgi \
    && a2enmod socache_shmcb

# Install additional packages
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libpq-dev \
    zlib1g-dev \
    libxml2-dev \
    libonig-dev \
    libapache2-mod-shib \
    git \
    unzip \
    libzip-dev

RUN a2enmod shib

# PHP extensions including zip
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd zip

# Configure PHP to send errors to STDOUT instead of browser
RUN echo "error_reporting = E_ALL" > /usr/local/etc/php/conf.d/error-logging.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/conf.d/error-logging.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/error-logging.ini \
    && echo "error_log = /dev/stdout" >> /usr/local/etc/php/conf.d/error-logging.ini

# Set ServerName to suppress Apache warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Create httpdapp directory
RUN mkdir -p /httpdapp/public

# Set working directory
#WORKDIR /httpdapp

# Copy composer files first for caching
COPY composer.json composer.lock ./

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Copy the entire project
COPY . /httpdapp

# Create a startup script that waits for files to be copied before starting Apache
RUN echo '#!/bin/bash\n\
echo "Waiting for files to be copied to /var/www/html/public..."\n\
while [ ! -d "/var/www/html/public" ] || [ -z "$(ls -A /var/www/html/public)" ]; do\n\
  sleep 1\n\
done\n\
echo "Files are ready, regenerating autoloader..."\n\
cd /var/www/html && composer dump-autoload --optimize\n\
echo "Starting Apache..."\n\
exec apache2-foreground\n\
' > /usr/local/bin/start-apache.sh && \
chmod +x /usr/local/bin/start-apache.sh

# Use the startup script as the entrypoint
CMD ["/usr/local/bin/start-apache.sh"]
