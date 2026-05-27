<?php
/*
 * VULNERABLE: extension check looks at the trailing segment only, AND the
 * destination directory is configured with `AddHandler application/x-httpd-php
 * .php` (see uploads/double-ext/.htaccess). That combination is the classic
 * double-extension bypass:
 *
 *   shell.php.jpg  -> pathinfo(..., PATHINFO_EXTENSION) returns "jpg",
 *                     check passes, file is stored verbatim, Apache
 *                     executes it because the name contains `.php`.
 *   shell.jpg.php  -> some variant tooling normalises to .jpg before
 *                     checking; same outcome here either way.
 *
 * The lesson the companion article draws: extension parsing and Apache
 * handler resolution are two different state machines, and "is this
 * extension safe?" only matters if your webserver agrees on which
 * extension is the trailing one. AddHandler vs SetHandler is the version
 * of that mismatch that has shipped in countless production configs.
 */
require_once __DIR__ . '/shared/layout.php';

$BLOCKED = ['php', 'phtml', 'php3', 'php4', 'phar', 'pht'];

$msg = null;
$ok  = false;
$url = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $f = $_FILES['file'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Upload error (code ' . (int)$f['error'] . ').';
    } else {
        $name = basename($f['name']);
        // Naive "the extension is whatever follows the last dot" parse.
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, $BLOCKED, true)) {
            $msg = 'Blocked trailing extension: ' . $ext;
        } else {
            $dest = __DIR__ . '/uploads/double-ext/' . $name;
            if (move_uploaded_file($f['tmp_name'], $dest)) {
                $ok  = true;
                $url = '/uploads/double-ext/' . rawurlencode($name);
                $msg = 'Stored as ' . $name . ' (trailing extension: ' . $ext . ')';
            } else {
                $msg = 'Could not move uploaded file.';
            }
        }
    }
}

layout_open('Double-extension upload');
?>
<h1>Double-extension upload</h1>
<p>Checks only the trailing extension against a blacklist (case-insensitive this time). Stored in <code>/uploads/double-ext/</code>, which is configured with the unsafe <code>AddHandler</code> directive.</p>

<?php if ($msg): ?>
  <div class="alert <?php echo $ok ? 'ok' : ''; ?>">
    <?php echo h($msg); ?>
    <?php if ($ok && $url): ?>
      <br>Served at <a href="<?php echo h($url); ?>"><?php echo h($url); ?></a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
  <label>File</label>
  <input type="file" name="file" required>
  <button type="submit">Upload</button>
</form>

<?php layout_close(); ?>
