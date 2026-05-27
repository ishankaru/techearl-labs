<?php
/*
 * VULNERABLE: zero validation. The file lands in the webroot under its
 * original name, and the destination directory is served by Apache with the
 * standard mod_php handler, so a `.php` upload is immediately executable at
 * /uploads/naive/<name>.php.
 *
 * This is the strawman case. Nobody writes code like this on purpose, but
 * it is the floor every other flavour of broken validation collapses back
 * to once the bypass works.
 */
require_once __DIR__ . '/shared/layout.php';

$msg = null;
$ok  = false;
$url = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $f = $_FILES['file'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Upload error (code ' . (int)$f['error'] . ').';
    } else {
        $name = basename($f['name']);
        $dest = __DIR__ . '/uploads/naive/' . $name;
        if (move_uploaded_file($f['tmp_name'], $dest)) {
            $ok  = true;
            $url = '/uploads/naive/' . rawurlencode($name);
            $msg = 'Stored as ' . $name;
        } else {
            $msg = 'Could not move uploaded file.';
        }
    }
}

layout_open('Naive upload');
?>
<h1>Naive upload</h1>
<p>No validation. The file is stored under its original name in <code>/uploads/naive/</code>.</p>

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
