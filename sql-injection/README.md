# SQL injection labs

Each lab is a target the companion TechEarl articles attack. Articles, labs, and the techniques each lab supports:

| Lab | Article | Techniques supported |
|---|---|---|
| `sqli-basic` | [sqlmap tutorial: exploiting a vulnerable app](https://techearl.com/sqlmap-tutorial-exploiting-a-vulnerable-app) | Boolean blind, error-based, time-based blind, UNION, second-order, header injection (User-Agent and X-Tenant-Id into analytics table), file read via `LOAD_FILE`, webshell write via `INTO OUTFILE` |

Future labs slot in here as the corresponding articles ship:

| Lab (planned) | Article | Focus |
|---|---|---|
| `sqli-blind` | (future) | Pure blind extraction at scale |
| `sqli-nosql` | (future) | MongoDB operator injection |
| `sqli-pivoting` | (future) | DBA + UDF for OS command execution |

## Running

```bash
docker compose up sqli-basic
```

Listens on `http://localhost:8080`.

## Tearing down

```bash
docker compose down -v
```

The `-v` flag drops the named volume, which is necessary if you want a clean reset (the webshell from the file-write step persists otherwise).

## Resetting between attempts

Same `-v` teardown, then `up` again. The seed runs on every fresh database start.
