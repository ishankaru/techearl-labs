FROM php:8.2-apache

# Static-ish internal "admin" panel. Lives on the Docker network only; the
# compose definition gives this service no published port. The point of the
# lab is that the vulnerable ssrf-basic container CAN reach this host, but
# the host operator (you) cannot, except via the SSRF in ssrf-basic.

COPY public/ /var/www/html/
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
