FROM php:8.2-apache

# PHAR runtime is part of the base image. We explicitly DO NOT set
# phar.readonly=Off in php.ini, because the demo writes its PHAR at
# runtime via a tiny CLI helper that runs as part of the build (see
# build_phar.php). The web app only ever reads PHARs, which is the
# realistic shape of a PHAR-deser bug: an attacker uploads a crafted
# archive elsewhere and the vulnerable code touches it with a filesystem
# function that triggers the metadata unserialize. The lab also writes
# one for you so you can curl it without crafting from scratch.
#
# We leave allow_url_include off (default) — PHAR deser does not need
# remote URL inclusion, it needs the phar:// stream wrapper which ships
# enabled in stock PHP. That is the whole point.

# Allow CLI helper to write a PHAR during the image build.
RUN echo 'phar.readonly = Off' > /usr/local/etc/php/conf.d/phar-writable.ini

# Drop the application code into the standard Apache docroot.
COPY public/ /var/www/html/
COPY src/ /var/www/src/
COPY build_phar.php /tmp/build_phar.php

# Sink for the gadget chain to write into. The deser-Logger gadget
# appends to /tmp/pwned; we pre-create the file world-writable so the
# www-data user can write to it on demand from the request handler.
RUN touch /tmp/pwned && chmod 666 /tmp/pwned

# Pre-build a crafted PHAR archive that contains a serialized Logger
# object in its metadata. The web request handler will then `file_exists`
# on a phar:// URL pointing at this file, triggering metadata unserialize.
RUN php /tmp/build_phar.php && rm /tmp/build_phar.php

# Now flip PHAR back to read-only so the web container cannot rewrite
# the archive at runtime (matches a real prod config).
RUN echo 'phar.readonly = On' > /usr/local/etc/php/conf.d/phar-writable.ini

RUN chown -R www-data:www-data /var/www/html /var/www/src

EXPOSE 80
