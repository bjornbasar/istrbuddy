# Stage 1: Install dependencies
FROM composer:latest AS deps
WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Stage 2: Production image
FROM php:8.3-apache

# Install SQLite dev lib, build pdo_sqlite extension, enable mod_rewrite
RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Set Apache document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/000-default.conf \
    /etc/apache2/apache2.conf

# Allow .htaccess overrides
RUN sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Copy app
WORKDIR /var/www/html
COPY --from=deps /app/vendor vendor/
COPY . .

# Writable DB directory for SQLite
RUN mkdir -p db && chown www-data:www-data db

# Entrypoint: seed DB if it doesn't exist, then start Apache
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
