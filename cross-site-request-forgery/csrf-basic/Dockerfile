FROM php:8.2-apache

# mod_rewrite is not strictly needed but keeping the toolchain consistent with
# the other PHP labs in this monorepo.
RUN a2enmod rewrite headers

# Drop the application code into the standard Apache docroot.
COPY public/ /var/www/html/

# The session.gc_maxlifetime default is fine; we only need writable session
# storage. The default /tmp is fine inside the container.
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
