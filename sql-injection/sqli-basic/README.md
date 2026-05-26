# sqli-basic

The companion lab for [TechEarl's sqlmap tutorial](https://techearl.com/sqlmap-tutorial-exploiting-a-vulnerable-app). A small PHP + MySQL app with several intentionally-vulnerable endpoints, designed to exercise every variant the tutorial walks through plus the header-injection step.

## Endpoints

| Endpoint | Method | Vulnerable parameter(s) | Injection context |
|---|---|---|---|
| `/product?id=1` | GET | `id` | Numeric concat into `SELECT ... WHERE id = $id` (3 columns). Boolean, error, time, UNION all work |
| `/search.php?q=foo` | GET | `q` | String concat into a `LIKE` clause |
| `/login` (POST) | POST | `username`, `password` | Both fields concatenated into `SELECT ... WHERE username = '$u' AND password = '$p'`. Login-bypass with `' OR '1'='1` |
| `/track?ref=<v>` | GET | `ref` | Parameterised (safe entry); intended as the second-order target once the read-back endpoint is added |
| `/track?page=<p>` | GET | `User-Agent` header, `X-Tenant-Id` header, `page` query | All three concatenated into an `INSERT` against `page_views`. Mirrors the analytics-logger anti-pattern |

## Database

- Database: `shop`
- Application user: `webapp` / `webapp123` (NOT a DBA)
- `FILE` privilege granted on the application user (so the article's Step 7 file read via `LOAD_FILE` and Step 8 file write via `INTO OUTFILE` both work)
- Tables: `products`, `users`, `sessions`, `audit_log`, `tracking`, `page_views`
- Three seed users: `admin / admin123`, `alice / alice123`, `bob / bob123`. Passwords stored as bcrypt at cost 12.

## Running

```bash
docker compose up sqli-basic
```

From the **root** of the `techearl-labs` repo. Lab listens on `http://localhost:8080`.

## Tearing down between attempts

```bash
docker compose down -v
```

The `-v` flag drops the named volumes, which is necessary if you wrote a webshell into the webroot during the file-write step and want a clean slate.

## Verifying it is up

```bash
# Catalogue renders, 5 products
curl -s http://localhost:8080/ | grep -c '<div class="product">'

# Single product by id, expect one
curl -s 'http://localhost:8080/product?id=1' | grep -c '<div class="product">'

# Confirm injection works
curl -s 'http://localhost:8080/product?id=-1%20UNION%20SELECT%201,user(),3-- -'
```

That last request should reflect `webapp@%` (the MySQL connection identity) into the page where the second column would normally render the product name. If it does, the lab is wired correctly.

## Safety

Bound to `127.0.0.1` by default. Do not expose this container to a public interface. Do not run anything else that uses port 8080 while it is up.
