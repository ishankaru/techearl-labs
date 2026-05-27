<?php
/*
 * VULNERABLE: extension blacklist. The four "obvious" PHP extensions are
 * blocked, but the check is broken in three ways at once:
 *
 *   1. The comparison is case-sensitive. mod_php on most distros is wired
 *      to AddType application/x-httpd-php .php (lowercase only in the
 *      config but Apache matches extensions case-insensitively), so
 *      `shell.phP` slips through the check and still executes.
 *   2. The list misses other executable extensions: `.pht`, `.phar`,
 *      `.phps`, `.php5`, `.php7`. Whether each one fires depends on the
 *      mod_php AddType/AddHandler config; `.phar` is very commonly
 *      executable because PHP's own packaging format reuses it.
 *   3. The check runs on the dotted suffix only. A leading-dot trick
 *      (`.htaccess`, `.user.ini`) is not on the list either, which would
 *      let an attacker rewrite the directory's PHP behaviour.
 *
 * Blacklists are the wrong shape for this problem. The companion article
 * walks through why an allowlist of expected MIME-and-magic-byte pairs is
 * the only validation that survives contact with reality.
 */
require_once __DIR__ . '/shared/layout.php';

$BLOCKED = ['php', 'phtml', 'php3', 'php4'];

$msg = null;
$ok  = false;
$url = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $f = $_FILES['file'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Upload error (code ' . (int)$f['error'] . ').';
    } else {
        $name = basename($f['name']);
        // Case-sensitive extension check against a fixed list. Realistic
        // anti-pattern: developer assumed pathinfo() lowercases the result
        // (it does not).
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        if (in_array($ext, $BLOCKED, true)) {
            $msg = 'Blocked extension: ' . $ext;
        } else {
            $dest = __DIR__ . '/uploads/blacklist/' . $name;
            if (move_uploaded_file($f['tmp_name'], $dest)) {
                $ok  = true;
                $url = '/uploads/blacklist/' . rawurlencode($name);
                $msg = 'Stored as ' . $name;
            } else {
                $msg = 'Could not move uploaded file.';
            }
        }
    }
}

layout_open('Blacklist upload');
?>
<h1>Blacklist upload</h1>
<p>Blocks the extensions <code>php</code>, <code>phtml</code>, <code>php3</code>, <code>php4</code> (case-sensitive). Anything else is accepted into <code>/uploads/blacklist/</code>.</p>

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
