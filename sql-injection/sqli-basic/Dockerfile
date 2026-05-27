FROM php:8.2-apache

# mysqli + PDO + pdo_mysql for the three DB-access patterns the lab demonstrates.
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Apache mod_rewrite for the friendly URLs (/product, /track via .htaccess).
RUN a2enmod rewrite

# .htaccess in /var/www/html ships rewrite rules for /product and /track. The
# default php-apache image has `AllowOverride None`, so .htaccess is ignored.
# Flip to All for /var/www/html only (other Directory blocks stay locked down).
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s|AllowOverride None|AllowOverride All|' /etc/apache2/apache2.conf

# Cross-container shared-volume permission: MySQL writes INTO OUTFILE with
# mode 0640 owned by uid:gid 999:999 (the mysql user inside the MySQL
# container). For Step 8 of the companion tutorial (writing a webshell to
# /var/www/html and serving it via Apache), the web container's www-data user
# must be a member of GID 999 so the group-read bit is effective.
RUN groupadd -g 999 mysqlshare 2>/dev/null || true \
    && usermod -aG 999 www-data

# Drop the application code (including .htaccess) into the standard Apache docroot.
COPY public/ /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# The compose definition mounts /var/www/html as a named volume shared with the
# MySQL container, so MySQL can write to the webroot for the `INTO OUTFILE`
# step. On first start Docker auto-populates the empty volume from this image.

EXPOSE 80
