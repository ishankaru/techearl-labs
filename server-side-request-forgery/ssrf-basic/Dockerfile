FROM php:8.2-apache

# curl extension for the blind-SSRF endpoint, which needs a real timeout knob
# that file_get_contents cannot give us cleanly. The basic /fetch.php endpoint
# stays on file_get_contents to keep the wrapper-abuse surface (file://,
# php://, gopher://, dict://, ftp://) deliberately wide.
RUN docker-php-ext-install curl

# Drop the application code into the standard Apache docroot.
COPY public/ /var/www/html/
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
