FROM php:8.2-apache

# Install required system packages & PHP extensions (SQLite, cURL, zip)
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libcurl4-openssl-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-install pdo pdo_sqlite curl \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www/html

# Copy all application files to the webroot
COPY . /var/www/html/

# Create runtime directories and set proper permissions
RUN mkdir -p /var/www/html/data/sessions \
    && chown -R www-data:www-data /var/www/html/data \
    && chmod -R 775 /var/www/html/data

# Configure Apache port dynamically for Render (Render sets $PORT, default to 10000 or 80)
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf \
    && sed -i 's/:80/:${PORT}/g' /etc/apache2/sites-available/000-default.conf

ENV PORT=10000

EXPOSE 10000

CMD ["sh", "-c", "apache2-foreground"]
