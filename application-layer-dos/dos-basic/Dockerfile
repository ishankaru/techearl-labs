FROM php:8.2-apache

# procps for `ps`/`top` checks; apache2-utils for `apache2ctl status`.
RUN apt-get update \
    && apt-get install -y --no-install-recommends procps apache2-utils \
    && rm -rf /var/lib/apt/lists/*

# Enable mod_status so the article can show busy workers with `apache2ctl status`.
RUN a2enmod status

# DEFAULT Apache config in this lab leaves Timeout very high so the
# slow-body endpoint can hold a worker. The defended endpoint takes care of
# itself with a PHP-level read timeout, so a single Apache instance is fine.
# We DO NOT change the global Timeout from its default here; that is the lab
# point. See README.
COPY apache-mods.conf /etc/apache2/conf-enabled/zz-dos-lab.conf

COPY public/ /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# Limit PHP memory so a runaway decompression cannot starve the host even
# if Docker mem_limit is somehow not applied.
RUN { \
      echo "memory_limit = 256M"; \
      echo "post_max_size = 200M"; \
      echo "upload_max_filesize = 200M"; \
      echo "max_execution_time = 60"; \
    } > /usr/local/etc/php/conf.d/zz-dos-lab.ini

EXPOSE 80
