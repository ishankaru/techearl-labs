FROM php:8.2-apache

# iputils-ping for /ping.php (provides /bin/ping, setuid in the Debian base).
# dnsutils for /lookup.php (provides /usr/bin/dig).
# procps is handy for the article's "the shell is real" sanity checks (ps, etc).
RUN apt-get update \
    && apt-get install -y --no-install-recommends iputils-ping dnsutils procps \
    && rm -rf /var/lib/apt/lists/*

# No PHP extensions, no DB. This lab is shell-out and eval only.

# Drop the application code into the standard Apache docroot.
COPY public/ /var/www/html/
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
