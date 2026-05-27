<?php
/*
 * VULNERABLE: accepts an SVG upload, trusts the client-supplied filename and
 * extension, stores it under /uploads/svg/, and exposes it back through the
 * /svg-view.php endpoint with Content-Type: image/svg+xml. SVG is XML and
 * supports <script>, event handlers, foreignObject HTML, and javascript:
 * URIs, so any payload inside the SVG body executes when a browser loads it
 * on the same origin as the application.
 *
 * The bug is twofold:
 *   1. No content sanitisation. The XML is stored verbatim.
 *   2. The serving path declares image/svg+xml, so the browser parses the
 *      response as an SVG document and runs whatever active content is in
 *      it on this origin.
 *
 * Companion article: /svg-xss
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
        // No MIME check, no extension allowlist beyond a trivial guard. The
        // attacker controls the stored filename. The whole point.
        $name = basename($f['name']);
        if ($name === '' || strpos($name, '/') !== false) {
            $msg = 'Bad filename.';
        } else {
            $dest = __DIR__ . '/uploads/svg/' . $name;
            if (move_uploaded_file($f['tmp_name'], $dest)) {
                $ok  = true;
                $url = '/svg-view.php?f=' . rawurlencode($name);
                $msg = 'Stored as ' . $name . '. View at ' . $url;
            } else {
                $msg = 'Could not move uploaded file.';
            }
        }
    }
}

layout_open('SVG upload (vulnerable)');
?>
<h1>SVG upload (vulnerable)</h1>
<p>Accepts any file, stores under <code>/uploads/svg/</code>, serves back through <code>/svg-view.php</code> with <code>Content-Type: image/svg+xml</code>. SVG XML is parsed as an active document by the browser, so a <code>&lt;script&gt;</code> inside the SVG runs on this origin.</p>

<?php if ($msg): ?>
  <div class="alert <?php echo $ok ? 'ok' : ''; ?>">
    <?php echo h($msg); ?>
    <?php if ($ok && $url): ?>
      <br>Served at <a href="<?php echo h($url); ?>"><?php echo h($url); ?></a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
  <label>SVG file</label>
  <input type="file" name="file" required>
  <button type="submit">Upload</button>
</form>

<p><small class="note">Try uploading an SVG containing <code>&lt;script&gt;alert(document.domain)&lt;/script&gt;</code> and opening the served URL in a browser.</small></p>

<?php layout_close(); ?>
