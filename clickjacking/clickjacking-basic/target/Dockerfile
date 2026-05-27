FROM php:8.2-apache

# Sessions are file-backed in /tmp via PHP's built-in session handler; no
# extensions needed. The vulnerability lives in response headers, not in
# any storage layer.

# Apache mod_rewrite for the friendly /dashboard, /confirm, /login routes.
RUN a2enmod rewrite

# Flip AllowOverride from None to All on /var/www/ so the docroot's
# .htaccess is honoured.
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s|AllowOverride None|AllowOverride All|' /etc/apache2/apache2.conf

COPY public/ /var/www/html/
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
