# clickjacking-basic

Two-container lab for the TechEarl [clickjacking spoke](https://techearl.com/clickjacking). One container is the framable victim application (`clickjacking-target` on `127.0.0.1:8090`), the other is the static attacker origin (`clickjacking-attacker` on `127.0.0.1:8091`) that hosts the PoC pages. Splitting them across two ports keeps the cross-origin framing relationship honest so `frame-ancestors` and `X-Frame-Options` can be exercised the way the browser actually enforces them.

## Running

```bash
docker compose up clickjacking-target clickjacking-attacker
```

From the root of the `techearl-labs` repo.

- Target: `http://localhost:8090`
- Attacker: `http://localhost:8091`

## Endpoints

### Target (`http://localhost:8090`)

| Path | Behaviour |
|---|---|
| `/` | Landing page, links to login and dashboards. |
| `/login` | Trivial login form. Any non-empty username sets a session cookie. |
| `/dashboard` | Vulnerable. NO `X-Frame-Options`. NO `Content-Security-Policy: frame-ancestors`. Framable by any origin. |
| `/confirm` | POST-only. The prize action; marks the session as deleted. |
| `/protected/dashboard` | Defended reference. Sets `X-Frame-Options: DENY` and `Content-Security-Policy: frame-ancestors 'none'`. |

### Attacker (`http://localhost:8091`)

| Path | Variant |
|---|---|
| `/` | Index, links to each PoC. |
| `/classic.html` | Transparent iframe over a decoy button. The classical UI-redress attack. |
| `/cursor-jacking.html` | Fake CSS cursor offset by 120px so the rendered cursor lies about click position. |
| `/double-clickjacking.html` | Paulos Yibelo 2024 variant. `window.open` + focus swap on the first half of a double-click. No iframe. |

## Manual reproduction

The HTTP-level checks below confirm the headers and the static assets are wired correctly. The actual UI-redress click cannot be automated reliably without a real browser, so the visual confirmation step is manual.

1. Boot both containers: `docker compose up clickjacking-target clickjacking-attacker`.
2. Open `http://localhost:8090/login` in Chrome (or any modern browser). Submit the form with any username. You are bounced to `/dashboard`.
3. Open `http://localhost:8091/classic.html` in the same browser profile (so the target session cookie rides along).
4. Hover the blue **SPIN** button. The framed dashboard sits on top of it at 15% opacity (lab visibility setting). Click SPIN. The click lands inside the iframe on the Delete account button. Return to `http://localhost:8090/dashboard` and observe the banner: *Your account was just deleted via /confirm.*
5. Repeat against `/protected/dashboard` by editing the iframe `src` in `classic.html`. The browser refuses to render it because `X-Frame-Options: DENY` and `frame-ancestors 'none'` are present. The iframe is blank; no click can land.
6. For double-clickjacking: from `http://localhost:8091/double-clickjacking.html`, double-click the purple Continue button at normal speed. A popup opens at `/dashboard` and the second click of the double-click lands on the popup&rsquo;s Delete account button while the popup has focus. There is no iframe here, so `frame-ancestors` and `X-Frame-Options` do nothing &mdash; the defence has to live on the sensitive page itself (focus duration check, deferred-enable, server-side step-up).

## Header verification

```bash
# Vulnerable route: must NOT carry X-Frame-Options or frame-ancestors.
curl -sI http://localhost:8090/dashboard | grep -iE 'x-frame-options|content-security-policy' || echo 'no framing defences present (expected)'

# Defended route: must carry both headers.
curl -sI http://localhost:8090/protected/dashboard | grep -iE 'x-frame-options|content-security-policy'

# Attacker pages render with HTTP 200.
curl -sI http://localhost:8091/ | head -n1
curl -sI http://localhost:8091/classic.html | head -n1
```

## Tearing down

```bash
docker compose down
```

No volumes are mounted; nothing to clean up beyond the containers.

## Safety

Both containers bind to `127.0.0.1` only. The vulnerability is real (any origin can frame `/dashboard` and route clicks to the Delete account form). None of this configuration belongs in a real application.
