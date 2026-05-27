<p align="center">
  <a href="https://techearl.com">
    <img src="assets/techearl-logo.png" alt="TechEarl" width="120">
  </a>
</p>

<h1 align="center">techearl-labs</h1>

<p align="center">
  Deliberately vulnerable applications used as worked examples in <a href="https://techearl.com">TechEarl</a> security articles. Each lab is a small, self-contained Docker target you can spin up locally, exploit by hand or with tooling, and tear down.
</p>

<p align="center">
  <strong>Use these only on systems you own.</strong> Every service binds to <code>127.0.0.1</code> by default. Never expose a lab container to the public internet: the vulnerabilities are real and will be exploited by automated scanners within hours of being reachable.
</p>

## Layout

```
techearl-labs/
├── docker-compose.yml                top-level: every lab as a named service
├── assets/                           README logo + shared media
├── docs/ARCHITECTURE.md              monorepo conventions for adding new labs
│
├── sql-injection/                    A03 OWASP, the original
│   └── sqli-basic/                   PHP + MySQL, classic + blind + UNION + file read
├── cross-site-scripting/
│   └── xss-basic/                    reflected, stored, DOM, cookie-theft chain
├── server-side-request-forgery/
│   └── ssrf-basic/                   internal admin + AWS IMDS mock + allowlist bypass
├── file-upload/
│   └── upload-basic/                 .phar handler, MIME bypass, polyglot RCE, SVG XSS
├── path-traversal/
│   └── lfi-basic/                    PHP wrappers, log poisoning, RFI
├── remote-code-execution/
│   └── rce-basic/                    OS command injection, escapeshellcmd argument bypass
├── xml-external-entity/
│   └── xxe-basic/                    in-band, blind OOB, XInclude, billion-laughs
│
├── insecure-deserialization/
│   └── deser-basic/                  PHP unserialize + PHAR metadata sinks
├── api-security-attacks/
│   └── api-basic/                    BOLA, mass assignment, JWT alg=none, GraphQL depth bomb
├── cross-site-request-forgery/
│   └── csrf-basic/                   classic, JSON-via-text/plain, broken-token, defended ref
├── clickjacking/
│   └── clickjacking-basic/           two services: target + attacker on separate origins
└── application-layer-dos/
    └── dos-basic/                    ReDoS, decompression bomb, slow-body, defended siblings
```

Each vulnerability class lives in its own top-level directory. Inside, labs are named by complexity (`-basic`, `-blind`, `-advanced`, etc.) so an article can reference exactly the right one.

## Quickstart

```bash
git clone https://github.com/ishankaru/techearl-labs.git
cd techearl-labs
docker compose up sqli-basic
```

Open `http://localhost:8080`. The companion article walks through every step: [sqlmap tutorial: exploiting a vulnerable app](https://techearl.com/sqlmap-tutorial-exploiting-a-vulnerable-app).

## Per-lab index

| Service | Port | Class | Companion article |
|---|---|---|---|
| `sqli-basic` | `8080` | SQL injection (in-band, blind, UNION, file read) | [sqlmap tutorial](https://techearl.com/sqlmap-tutorial-exploiting-a-vulnerable-app), [SQL injection deep dive](https://techearl.com/sql-injection) |
| `xss-basic` | `8081` | Cross-site scripting (reflected, stored, DOM, cookie theft) | [XSS deep dive](https://techearl.com/cross-site-scripting), [Dalfox tutorial](https://techearl.com/dalfox-tutorial-exploiting-a-vulnerable-app) |
| `ssrf-basic` | `8082` | SSRF (internal admin, AWS IMDS mock, allowlist bypass) | [SSRF deep dive](https://techearl.com/server-side-request-forgery), [SSRFmap tutorial](https://techearl.com/ssrfmap-tutorial-exploiting-a-vulnerable-app) |
| `upload-basic` | `8083` | File upload (`.phar`, MIME bypass, double extension, polyglot, SVG XSS) | [File upload deep dive](https://techearl.com/file-upload-vulnerabilities), [fuxploider tutorial](https://techearl.com/fuxploider-tutorial-exploiting-a-vulnerable-app), [image polyglot](https://techearl.com/image-polyglot-webshell), [SVG XSS](https://techearl.com/svg-xss) |
| `lfi-basic` | `8084` | Path traversal + PHP wrappers + log poisoning | [Path traversal deep dive](https://techearl.com/path-traversal), [LFImap tutorial](https://techearl.com/lfimap-tutorial-exploiting-a-vulnerable-app) |
| `rce-basic` | `8085` | OS command injection, argument injection via `escapeshellcmd` | [RCE deep dive](https://techearl.com/remote-code-execution), [commix tutorial](https://techearl.com/commix-tutorial-exploiting-a-vulnerable-app) |
| `xxe-basic` | `8086` | XXE (in-band, blind OOB, XInclude, billion-laughs) | [XXE deep dive](https://techearl.com/xml-external-entity), [XXEinjector tutorial](https://techearl.com/xxeinjector-tutorial-exploiting-a-vulnerable-app) |
| `deser-basic` | `8087` | Insecure deserialization (cookie, POST body, PHAR metadata) | [Deserialization deep dive](https://techearl.com/insecure-deserialization) |
| `api-basic` | `8088` | API security (BOLA, mass assignment, JWT alg=none, GraphQL depth bomb) | [API security deep dive](https://techearl.com/api-security-attacks) |
| `csrf-basic` | `8089` | CSRF (classic, JSON-via-text/plain, broken-token, defended ref) | [CSRF deep dive](https://techearl.com/cross-site-request-forgery) |
| `clickjacking-basic` | `8090` + `8091` | Clickjacking (classic, cursor-jacking, double-clickjacking) | [Clickjacking deep dive](https://techearl.com/clickjacking) |
| `dos-basic` | `8092` | L7 DoS (ReDoS, decompression bomb, slow-body) plus defended siblings | [Application-layer DoS deep dive](https://techearl.com/application-layer-dos) |

Each lab's own `README.md` has the full endpoint table, working exploit payloads, and the matching defence reference where one exists.

## Adding a new lab

1. Create a directory under the vulnerability class: `sql-injection/sqli-blind/`, `cross-site-scripting/xss-stored-advanced/`, etc.
2. Add a `Dockerfile`, the vulnerable app code, and any seed data.
3. Register the service in the root `docker-compose.yml`. Service name = directory name.
4. Bind to `127.0.0.1` on a port that does not collide with other labs (next free is `8093`).
5. Document the vulnerable endpoints in the lab's own `README.md`.
6. Update the per-lab index in this file.

See `docs/ARCHITECTURE.md` for the conventions in detail.

## Safety

- Every lab binds to `127.0.0.1` by default.
- No lab is intended to run on a public host, in a container that can reach internal services, or with credentials shared from another environment.
- Container credentials are intentionally weak (`webapp / webapp123`, etc.) so they read clearly in tutorial output. Never reuse them.
- The `dos-basic` lab has container-level CPU, memory, and PID caps in compose so a runaway payload cannot kill the host. Even with those caps, treat the lab as a one-shot resilience-test rig, not a continuous service.
- The MySQL `FILE` privilege is granted on the `sqli-basic` user intentionally, to support the file-read step of the companion article. Do not copy that grant into any real configuration.

## Licence

MIT. See `LICENCE`.
