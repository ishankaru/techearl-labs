FROM php:8.2-apache

# libxml ships with the base php image; no extra PHP extensions needed for
# DOMDocument / libxml-based parsing. We do NOT install any hardening
# wrappers, and the application code explicitly re-enables the unsafe
# libxml flags that older PHP releases had on by default. That is the lab.

# Drop the application code into the standard Apache docroot.
COPY public/ /var/www/html/
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
