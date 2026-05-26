FROM php:8.2-apache

# mysqli + PDO + pdo_mysql for the three DB-access patterns the lab demonstrates.
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Apache mod_rewrite is convenient for the friendly URLs the app uses.
RUN a2enmod rewrite

# Drop the application code into the standard Apache docroot.
COPY public/ /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# The compose definition mounts /var/www/html as a named volume shared with the
# MySQL container, so MySQL can write to the webroot for the `INTO OUTFILE`
# step. On first start Docker auto-populates the empty volume from this image.

EXPOSE 80
