<?php
/*
 * VULNERABLE: trusts the client-supplied Content-Type. PHP populates
 * $_FILES[...]['type'] from the multipart `Content-Type` header that the
 * client sent in the request body. It is not derived from the bytes on
 * disk; the server never inspects them. Anyone with curl can claim any
 * MIME type they want:
 *
 *   curl -F 'file=@shell.php;type=image/jpeg' http://localhost:8083/upload-mime.php
 *
 * The `;type=image/jpeg` segment is the attacker-controlled value. The
 * file is still a PHP script, the extension is still `.php`, and the
 * destination directory still executes PHP, so the upload turns into
 * RCE in one request.
 *
 * The right shape is finfo_file() (or the `file` binary) against the
 * temporary upload path, plus an allowlist of accepted MIME types, plus a
 * server-controlled extension on the stored file. The companion article
 * covers each of those.
 */
require_once __DIR__ . '/shared/layout.php';

$ALLOWED_MIME = ['image/jpeg'];

$msg = null;
$ok  = false;
$url = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $f = $_FILES['file'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Upload error (code ' . (int)$f['error'] . ').';
    } else {
        // $_FILES[...]['type'] is the client's claim, not a fact. Treating
        // it as authoritative is the bug.
        $claimed = $f['type'] ?? '';
        if (!in_array($claimed, $ALLOWED_MIME, true)) {
            $msg = 'Rejected: Content-Type ' . $claimed . ' is not on the allowlist.';
        } else {
            $name = basename($f['name']);
            $dest = __DIR__ . '/uploads/mime/' . $name;
            if (move_uploaded_file($f['tmp_name'], $dest)) {
                $ok  = true;
                $url = '/uploads/mime/' . rawurlencode($name);
                $msg = 'Stored as ' . $name . ' (Content-Type: ' . $claimed . ')';
            } else {
                $msg = 'Could not move uploaded file.';
            }
        }
    }
}

layout_open('MIME upload');
?>
<h1>MIME upload</h1>
<p>Accepts files whose multipart <code>Content-Type</code> is <code>image/jpeg</code>. Stored in <code>/uploads/mime/</code>.</p>

<?php if ($msg): ?>
  <div class="alert <?php echo $ok ? 'ok' : ''; ?>">
    <?php echo h($msg); ?>
    <?php if ($ok && $url): ?>
      <br>Served at <a href="<?php echo h($url); ?>"><?php echo h($url); ?></a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
  <label>File (browser sets Content-Type from the file's extension)</label>
  <input type="file" name="file" required>
  <button type="submit">Upload</button>
</form>

<p><small class="note">The browser form sets Content-Type from the local file extension. To bypass, drive the endpoint with <code>curl -F</code> and pin the MIME claim yourself.</small></p>

<?php layout_close(); ?>
