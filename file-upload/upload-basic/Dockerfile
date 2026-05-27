FROM php:8.2-apache

# mod_rewrite is not strictly needed here, but the other labs enable it so
# leave the toolchain consistent for anyone copying this Dockerfile as a
# starting point.
RUN a2enmod rewrite

# ImageMagick + exiftool for the polyglot endpoints (/upload-imgproc.php and
# the workflow the image-polyglot-webshell article walks). PHP-GD is bundled
# via the gd extension so /upload-strict.php can re-encode uploads.
RUN apt-get update \
    && apt-get install -y --no-install-recommends imagemagick libmagickwand-dev libpng-dev libjpeg-dev libgif-dev libfreetype6-dev libexif-dev exiftool \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install gd exif \
    && rm -rf /var/lib/apt/lists/*

# AllowOverride All on /var/www/ so the per-directory .htaccess files shipped
# with the lab (specifically uploads/double-ext/.htaccess, which sets the unsafe
# AddHandler) are honoured. The default php-apache image has AllowOverride None.
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s|AllowOverride None|AllowOverride All|' /etc/apache2/apache2.conf

# Extend the PHP handler to also execute .phar (PHP archive) files. The default
# docker-php.conf only matches \.php$, but many real-world Apache installs add
# .phar (and sometimes .phtml, .pht) to the executable extension list because
# a tool they shipped needed PHAR support and the sysadmin grepped for "phar"
# in the docs without thinking through the upload-handler consequence. The
# blacklist endpoint forgets to block phar, so this is the bypass the article
# walks: upload shell.phar, Apache treats it as PHP, exploit fires.
RUN { \
      echo '<FilesMatch "\.phar$">'; \
      echo '    SetHandler application/x-httpd-php'; \
      echo '</FilesMatch>'; \
    } >> /etc/apache2/conf-available/docker-php.conf

# Drop the application code into the standard Apache docroot.
COPY public/ /var/www/html/

# www-data needs write on every upload destination. The uploads/ tree is also
# served by Apache, which is the whole point of the lab: a writable directory
# that the webserver will happily execute PHP out of.
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 0775 /var/www/html/uploads

EXPOSE 80
