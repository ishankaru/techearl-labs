# Architecture of techearl-labs

A monorepo of intentionally vulnerable Docker targets organised by vulnerability class. Each lab is a self-contained Docker setup that the corresponding TechEarl article walks through end-to-end.

## Conventions

- **Top-level directories** are vulnerability classes that map 1:1 to TechEarl content silos: `sql-injection/`, `xss/`, `csrf/`, `ssrf/`, `rce/`, `file-upload/`, `path-traversal/`, etc.
- **Each class directory** contains one or more lab subdirectories named by complexity: `<class>-basic`, `<class>-blind`, `<class>-advanced`, `<class>-second-order`. The basic lab is the first one ships; others are added as the corresponding article ships.
- **Each lab** has its own `Dockerfile`, `README.md`, vulnerable application code, and any seed data. Labs are self-contained except for the root `docker-compose.yml` that names every service.
- **Service names** in `docker-compose.yml` match the directory name (`sqli-basic`, `xss-stored`, etc.) so the article can say `docker compose up sqli-basic` without ambiguity.
- **Port assignments** are tracked in the root `docker-compose.yml`. Each lab binds to `127.0.0.1` on a port that does not collide with any other lab; the basic SQL injection lab takes 8080, the next labs use 8081, 8082, etc.

## Adding a new lab

1. Create the directory: `mkdir -p <class>/<lab>/public` (Apache+PHP labs) or `mkdir -p <class>/<lab>` for other stacks.
2. Add a `Dockerfile`. Apache+PHP labs use `php:8.2-apache` as the base. Node labs use `node:lts-alpine`. Match the stack the vulnerability typically lives in.
3. Add the vulnerable application code under the lab directory. Keep it small: every file should be there to demonstrate the vulnerability or to give the lab a realistic shape.
4. Add a `README.md` that lists every vulnerable endpoint, the database schema (if any), and how to verify the lab is wired correctly with one or two `curl` commands.
5. Add the service to the root `docker-compose.yml`. Mirror the existing structure: build context, container name matching service name, port mapping on `127.0.0.1`, named volumes for any cross-container file sharing.
6. Update the **Per-lab index** in the root `README.md`.

## Safety stance

Every service binds to `127.0.0.1` by default. Hosting any of these on a public interface is exposing a known-vulnerable application to the internet, and they will be exploited within hours. The companion articles include an authorisation disclaimer for the same reason.

## What goes in this repo vs the article repo

- Vulnerable application code, schema, Docker setup, lab-specific README → here.
- The exploit walkthrough, defence guidance, sqlmap commands, CVE references → in the corresponding TechEarl article. Cross-link both directions: the article links the lab, the lab README links the article.
