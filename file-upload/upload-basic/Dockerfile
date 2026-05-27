FROM php:8.2-apache

# mod_rewrite is not strictly needed here, but the other labs enable it so
# leave the toolchain consistent for anyone copying this Dockerfile as a
# starting point.
RUN a2enmod rewrite

# AllowOverride All on /var/www/ so the per-directory .htaccess files shipped
# with the lab (specifically uploads/double-ext/.htaccess, which sets the unsafe
# AddHandler) are honoured. The default php-apache image has AllowOverride None.
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s|AllowOverride None|AllowOverride All|' /etc/apache2/apache2.conf

# Drop the application code into the standard Apache docroot.
COPY public/ /var/www/html/

# www-data needs write on every upload destination. The uploads/ tree is also
# served by Apache, which is the whole point of the lab: a writable directory
# that the webserver will happily execute PHP out of.
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 0775 /var/www/html/uploads

EXPOSE 80
