# techearl-labs

Deliberately vulnerable applications used as worked examples in [TechEarl](https://techearl.com) security articles. Each lab is a small, self-contained Docker target that you can spin up locally, exploit by hand or with tooling, and tear down.

**Use these only on systems you own.** Bind every service to `127.0.0.1` by default; never expose a lab container to the public internet. The vulnerabilities are real and will be exploited by automated scanners within hours of being reachable.

## Layout

```
techearl-labs/
├── docker-compose.yml              top-level: every lab as a named service
├── sql-injection/
│   ├── README.md                   class overview, which articles use which labs
│   └── sqli-basic/                 PHP + MySQL classic SQL injection lab
│       ├── Dockerfile
│       ├── public/                 vulnerable PHP app
│       ├── seed.sql                schema + seed data + grants
│       └── README.md
├── xss/                            (future: stored, reflected, DOM-based labs)
├── csrf/                           (future)
├── ssrf/                           (future)
└── docs/
    └── ARCHITECTURE.md             monorepo conventions for adding new labs
```

Each vulnerability class lives in its own top-level directory. Inside, labs are named by complexity (`-basic`, `-blind`, `-advanced`, etc.) so an article can reference exactly the right one.

## Quickstart

```bash
git clone https://github.com/ishankaru/techearl-labs.git
cd techearl-labs
docker compose up sqli-basic
```

The first lab listens on `http://localhost:8080`. Hit `/product?id=1`, `/search?q=foo`, or `POST /login` to see the vulnerable endpoints. The companion article walks through every step: [sqlmap tutorial: exploiting a vulnerable app](https://techearl.com/sqlmap-tutorial-exploiting-a-vulnerable-app).

## Per-lab index

| Service | Article | Class |
|---|---|---|
| `sqli-basic` | [sqlmap tutorial](https://techearl.com/sqlmap-tutorial-exploiting-a-vulnerable-app) | SQL injection (in-band + blind + UNION + file read) |

More labs ship as the corresponding articles do.

## Adding a new lab

1. Create a directory under the vulnerability class: `sql-injection/sqli-blind/`, `xss/xss-stored/`, etc.
2. Add a `Dockerfile`, the vulnerable app code, and any seed data.
3. Register the service in the root `docker-compose.yml`. Service name = directory name.
4. Bind to `127.0.0.1` on a port that does not collide with other labs.
5. Document the vulnerable endpoints in the lab's own `README.md`.
6. Update the per-lab index in this file.

See `docs/ARCHITECTURE.md` for the conventions in detail.

## Safety

- Every lab binds to `127.0.0.1` by default.
- No lab is intended to run on a public host, in a container that can reach internal services, or with credentials shared from another environment.
- Container credentials are intentionally weak (`webapp / webapp123`, etc.) so they read clearly in tutorial output. Never reuse them.
- The MySQL `FILE` privilege is granted on the `sqli-basic` user intentionally, to support the file-read step of the companion article. Do not copy that grant into any real configuration.

## Licence

MIT. See `LICENCE`.
