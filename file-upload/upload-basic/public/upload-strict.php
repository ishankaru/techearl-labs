<?php
/*
 * DEFENDED: extension allowlist, libmagic check, then a full re-encode
 * through PHP-GD that produces a brand-new image file from the decoded
 * pixel data. Any EXIF payload, appended trailer, polyglot tail, or
 * non-image bytes in the original are discarded because GD only writes
 * back what it decoded. The output is a clean JPEG.
 *
 * The stored file gets a random name with the allowed extension. There is
 * no include() sink reachable from here; the file is served as a static
 * download by Apache from /uploads/strict/, which has Options -ExecCGI in
 * its .htaccess so even a stray .php would not run.
 *
 * Compare with /upload-imgproc.php to see the polyglot attack succeed
 * against magic-byte-only validation and fail here.
 */
require_once __DIR__ . '/shared/layout.php';

$ALLOWED_EXT  = ['jpg', 'jpeg', 'png', 'gif'];
$ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/gif'];

$msg = null;
$ok  = false;
$url = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $f = $_FILES['file'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Upload error (code ' . (int)$f['error'] . ').';
    } else {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $ALLOWED_EXT, true)) {
            $msg = 'Rejected: extension .' . $ext . ' is not on the allowlist.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($f['tmp_name']);
            if (!in_array($mime, $ALLOWED_MIME, true)) {
                $msg = 'Rejected: libmagic reported ' . $mime . '.';
            } else {
                // Full re-encode through GD. Decode pixels, write a fresh
                // file. Anything that was not actual image data is gone.
                $img = null;
                switch ($mime) {
                    case 'image/jpeg': $img = @imagecreatefromjpeg($f['tmp_name']); break;
                    case 'image/png':  $img = @imagecreatefrompng($f['tmp_name']); break;
                    case 'image/gif':  $img = @imagecreatefromgif($f['tmp_name']); break;
                }
                if (!$img) {
                    $msg = 'Rejected: GD could not decode the file as an image.';
                } else {
                    $stored = bin2hex(random_bytes(8)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
                    $dest   = __DIR__ . '/uploads/strict/' . $stored;
                    switch ($mime) {
                        case 'image/jpeg': imagejpeg($img, $dest, 90); break;
                        case 'image/png':  imagepng($img, $dest); break;
                        case 'image/gif':  imagegif($img, $dest); break;
                    }
                    imagedestroy($img);
                    $ok  = true;
                    $url = '/uploads/strict/' . rawurlencode($stored);
                    $msg = 'Stored as ' . $stored . ' (re-encoded by GD).';
                }
            }
        }
    }
}

layout_open('Strict upload');
?>
<h1>Strict upload (defended)</h1>
<p>Extension allowlist, libmagic MIME check, then full GD re-encode. Stored under a random name in <code>/uploads/strict/</code>, served with <code>Options -ExecCGI</code>.</p>

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
