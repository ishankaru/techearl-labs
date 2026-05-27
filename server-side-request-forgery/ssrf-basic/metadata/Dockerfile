FROM nginx:1.27-alpine

# Mock AWS IMDSv1 (Instance Metadata Service v1). The real service lives at
# the link-local address 169.254.169.254 inside EC2 instances and exposes
# instance metadata over plain HTTP with no authentication, which is exactly
# what makes IMDSv1 the canonical SSRF cloud-credential exfil target.
#
# Docker containers cannot bind to link-local addresses, so this mock serves
# the same path layout at a Docker-network hostname (ssrf-basic-metadata).
# The companion article will show the real-world 169.254.169.254 form
# alongside the lab form so the substitution is explicit.
#
# Listens on the Docker network only; no published port.

COPY html/ /usr/share/nginx/html/

EXPOSE 80
