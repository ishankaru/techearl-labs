<?php
/*
 * VULNERABLE: passes the uploaded file to ImageMagick's `identify` via
 * shell_exec. The validator on the front side accepts any file whose
 * libmagic-reported MIME starts with `image/`, which a real JPEG with PHP
 * appended to an EXIF Comment field will satisfy (the JPEG magic bytes are
 * intact). The server then hands the file to the ImageMagick `identify`
 * binary, which on a vulnerable build will follow MVG/MSL coders into
 * shell commands (CVE-2016-3714, ImageTragick). Even on a hardened build
 * the file lives in the web root with its original extension, so an
 * include() sink elsewhere (see /view.php) executes the EXIF payload.
 *
 * This endpoint exists to demonstrate two polyglot attack paths:
 *   1. ImageTragick MVG payload reaches the `identify` binary.
 *   2. EXIF-embedded PHP webshell stored, then triggered via /view.php.
 *
 * Do not copy this code into anything real.
 */
require_once __DIR__ . '/shared/layout.php';

$msg = null;
$ok  = false;
$url = null;
$identify_out = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $f = $_FILES['file'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Upload error (code ' . (int)$f['error'] . ').';
    } else {
        // Server-side magic-byte check. Looks defensive. Passes for any real
        // JPEG/PNG/GIF, including a real JPEG with PHP appended to EXIF.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($f['tmp_name']);
        if (strpos($mime, 'image/') !== 0) {
            $msg = 'Rejected: libmagic reported ' . $mime . ', not an image.';
        } else {
            $name = basename($f['name']);
            $dest = __DIR__ . '/uploads/imgproc/' . $name;
            if (move_uploaded_file($f['tmp_name'], $dest)) {
                // The vulnerable bit: hand the uploaded file to the
                // ImageMagick `identify` binary. On an unhardened build, an
                // MVG/MSL coder payload triggers RCE here. On hardened
                // builds the call is harmless and the file just sits on
                // disk waiting for /view.php to include() it.
                $identify_out = shell_exec('identify ' . escapeshellarg($dest) . ' 2>&1');
                $ok  = true;
                $url = '/uploads/imgproc/' . rawurlencode($name);
                $msg = 'Stored as ' . $name . ' (libmagic: ' . $mime . ')';
            } else {
                $msg = 'Could not move uploaded file.';
            }
        }
    }
}

layout_open('Image-processing upload');
?>
<h1>Image-processing upload</h1>
<p>Magic-byte check (must start with <code>image/</code>), then hands the file to ImageMagick <code>identify</code>. Stored in <code>/uploads/imgproc/</code>.</p>

<?php if ($msg): ?>
  <div class="alert <?php echo $ok ? 'ok' : ''; ?>">
    <?php echo h($msg); ?>
    <?php if ($ok && $url): ?>
      <br>Served at <a href="<?php echo h($url); ?>"><?php echo h($url); ?></a>
      <br>View via include: <a href="/view.php?img=<?php echo h(basename($url)); ?>">/view.php?img=<?php echo h(basename($url)); ?></a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($identify_out !== null): ?>
  <h2>identify output</h2>
  <pre style="background:#0f172a;color:#e2e8f0;padding:.75rem;border-radius:.25rem;font-size:12px;overflow:auto"><?php echo h($identify_out); ?></pre>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
  <label>File</label>
  <input type="file" name="file" required>
  <button type="submit">Upload</button>
</form>

<p><small class="note">Drive with <code>curl -F 'file=@polyglot.jpg' http://localhost:8083/upload-imgproc.php</code>.</small></p>

<?php layout_close(); ?>
