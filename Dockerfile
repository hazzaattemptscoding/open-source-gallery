FROM php:8.2-apache

# Install required extensions
RUN docker-php-ext-install pdo pdo_mysql gd zip exif

# Enable mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/public/media
RUN chmod -R 755 /var/www/html/storage /var/www/html/public/media

# Copy Apache config to use public/ as document root
RUN echo '<VirtualHost *:80>\n  DocumentRoot /var/www/html/public\n  <Directory /var/www/html/public>\n    Options -MultiViews\n    RewriteEngine On\n    RewriteCond %{REQUEST_FILENAME} !-f\n    RewriteCond %{REQUEST_FILENAME} !-d\n    RewriteRule ^ index.php [QSA,L]\n  </Directory>\n</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

EXPOSE 80
