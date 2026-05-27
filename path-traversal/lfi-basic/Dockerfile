FROM php:8.2-apache

# No PHP extensions needed. The lab uses only the standard include()/require()
# machinery and the built-in php:// stream wrappers (filter, input) which ship
# with core PHP. Apache mod_rewrite is not used here either, the routing is
# done in index.php.

# Custom php.ini overrides. Every directive here is the INTENTIONAL unsafe
# setting that turns a plain Local File Inclusion into the worst-case
# RCE / source-disclosure pattern the companion article demonstrates.
#
#   allow_url_include=On   permits include() of remote URLs and the php://input
#                          stream. This is the single flag that turns LFI into
#                          unauthenticated RCE via POST body. Off by default
#                          since PHP 5.2 and intentionally re-enabled here.
#   allow_url_fopen=On     on by default in PHP, but pinned explicitly because
#                          the article calls it out. Needed for php://filter
#                          source disclosure.
#   display_errors=On      include() failures leak the absolute filesystem path
#                          the engine tried to open. That path is the primary
#                          oracle a blind LFI attacker uses to confirm the
#                          traversal depth. Real production sets this to Off.
RUN { \
      echo 'allow_url_include=On'; \
      echo 'allow_url_fopen=On'; \
      echo 'display_errors=On'; \
      echo 'display_startup_errors=On'; \
      echo 'error_reporting=E_ALL'; \
    } > /usr/local/etc/php/conf.d/zz-lfi-lab.ini

# Apache access log readability. Two problems to solve before the log-poisoning
# exploit chain (Step 4 in the README) can fire:
#
# 1. The base php:8.2-apache image symlinks /var/log/apache2/access.log to
#    /dev/stdout so docker logs picks up Apache's traffic. include()-ing a
#    character device does not read the log lines back as PHP source. The
#    symlink is replaced here with a real file so the include() of the log
#    parses and executes the injected <?php ... ?> tag.
#
# 2. Debian's apache2 writes the file as root:adm 0640. The www-data process
#    that handles PHP requests is NOT in the adm group, so include() of the
#    log would fail with permission-denied even with a real file. www-data is
#    added to adm so the group-read bit applies. This mirrors the common
#    real-world misconfiguration: shipping a containerised PHP app whose
#    www-data has been quietly granted log-read for a "debug dashboard"
#    that nobody removed before production.
RUN rm -f /var/log/apache2/access.log /var/log/apache2/error.log \
    && touch /var/log/apache2/access.log /var/log/apache2/error.log \
    && chown root:adm /var/log/apache2/access.log /var/log/apache2/error.log \
    && chmod 0640 /var/log/apache2/access.log /var/log/apache2/error.log \
    && usermod -aG adm www-data

# Drop the vulnerable app into the standard Apache docroot.
COPY public/ /var/www/html/
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
