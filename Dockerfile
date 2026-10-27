FROM php:8.2-apache

# Install system dependencies and PHP extensions needed for Laravel
# GD needs WebP (profile photos are saved as WebP) and exif turns sideways phone photos upright.
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd exif pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# PHP's defaults (2 MB per file, 8 MB per form) are below what the site accepts: 5 MB photos and documents,
# and product forms of up to 40 MB. Resizing a large phone photo also needs more than the default 128 MB of memory.
RUN printf "upload_max_filesize=10M\npost_max_size=40M\nmemory_limit=256M\n" > /usr/local/etc/php/conf.d/uploads.ini

# Enable Apache Mod_Rewrite for Laravel routing
RUN a2enmod rewrite

# Set Apache Document Root to Laravel's public folder
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copy your existing project files into the container
WORKDIR /var/www/html
COPY . .

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# Set permissions for Laravel storage and cache folders
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80
