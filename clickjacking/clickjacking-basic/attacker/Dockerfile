FROM nginx:alpine

# Static attacker pages. The container's only job is to serve the
# three PoC HTML files on a separate origin from clickjacking-target
# so frame-ancestors can be exercised honestly.

COPY public/ /usr/share/nginx/html/

EXPOSE 80
