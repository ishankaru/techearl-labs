FROM python:3.12-alpine

# Tiny collaborator server. Stands in for Burp Collaborator / interactsh
# inside the lab network: serves a single DTD file used by the blind-XXE
# payload, and logs every incoming request line (method, path, query) to
# stdout so the exfiltrated bytes are visible via `docker compose logs`.

WORKDIR /srv
COPY evil.dtd /srv/evil.dtd
COPY server.py /srv/server.py

EXPOSE 80
CMD ["python", "/srv/server.py"]
