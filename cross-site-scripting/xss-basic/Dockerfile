FROM php:8.2-apache

# mysqli is all we need for the XSS lab. The injection is in the HTML
# rendering layer, not the data layer.
RUN docker-php-ext-install mysqli

# Apache mod_rewrite for friendly URLs via .htaccess.
RUN a2enmod rewrite

# The default php-apache image has `AllowOverride None`, so .htaccess in the
# docroot is ignored. Flip to All for /var/www/html only.
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s|AllowOverride None|AllowOverride All|' /etc/apache2/apache2.conf

# Drop the application code (including .htaccess) into the standard Apache docroot.
COPY public/ /var/www/html/
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
