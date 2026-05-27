<?php
/*
 * DEFENDED reference for SVG uploads. Three layers, in order:
 *
 *   1. libmagic sniff: the file's bytes must read as image/svg+xml or
 *      text/xml (libmagic does not always commit to image/svg+xml even on
 *      a real SVG, so both are accepted).
 *   2. A hand-rolled XML scrub that strips <script>, <foreignObject>,
 *      <iframe>, <object>, <embed>, every on* event-handler attribute,
 *      every javascript: and data: URI in href/xlink:href, every <style>
 *      block, and the <animate>/<set>/<animateTransform> family that can
 *      carry javascript: in their `values`. Regex-based scrubs are a known
 *      weak pattern (the article references enshrined/svg-sanitize as the
 *      production-grade alternative); this is the lab's good-enough version
 *      for showing the defence works against the canonical payloads.
 *   3. The response sets Content-Disposition: attachment so even a residual
 *      bypass forces the browser to download the file instead of rendering
 *      it as an active document, plus X-Content-Type-Options: nosniff.
 */
require_once __DIR__ . '/shared/layout.php';

function strict_sanitize_svg(string $xml): string {
    // Drop dangerous elements (with or without content).
    $bad_elements = ['script', 'foreignObject', 'iframe', 'object', 'embed', 'style', 'animate', 'animateTransform', 'animateMotion', 'set', 'handler', 'use'];
    foreach ($bad_elements as $el) {
        $xml = preg_replace('#<\s*' . $el . '\b[^>]*>.*?<\s*/\s*' . $el . '\s*>#is', '', $xml);
        $xml = preg_replace('#<\s*' . $el . '\b[^>]*/?>#is', '', $xml);
    }
    // Drop every on* event-handler attribute (onload, onclick, onerror,
    // onmouseover, ...).
    $xml = preg_replace('#\son[a-z]+\s*=\s*"[^"]*"#i', '', $xml);
    $xml = preg_replace("#\son[a-z]+\s*=\s*'[^']*'#i", '', $xml);
    $xml = preg_replace('#\son[a-z]+\s*=\s*[^\s>]+#i', '', $xml);
    // Drop javascript: and data: URIs in href and xlink:href.
    $xml = preg_replace('#\s(?:xlink:)?href\s*=\s*"(?:\s*javascript|\s*data)\s*:[^"]*"#i', '', $xml);
    $xml = preg_replace("#\s(?:xlink:)?href\s*=\s*'(?:\s*javascript|\s*data)\s*:[^']*'#i", '', $xml);
    return $xml;
}

$msg = null;
$ok  = false;
$url = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $f = $_FILES['file'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Upload error (code ' . (int)$f['error'] . ').';
    } else {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($f['tmp_name']);
        if (!in_array($mime, ['image/svg+xml', 'image/svg', 'text/xml', 'application/xml', 'text/html', 'text/plain'], true)) {
            $msg = 'Rejected: libmagic reported ' . $mime . '. Only SVG XML accepted.';
        } else {
            $body = file_get_contents($f['tmp_name']);
            if (strpos($body, '<svg') === false) {
                $msg = 'Rejected: file body does not contain an <svg> root element.';
            } else {
                $clean = strict_sanitize_svg($body);
                $stored = bin2hex(random_bytes(8)) . '.svg';
                $dest   = __DIR__ . '/uploads/svg-strict/' . $stored;
                if (file_put_contents($dest, $clean) !== false) {
                    $ok  = true;
                    $url = '/svg-strict-view.php?f=' . rawurlencode($stored);
                    $msg = 'Stored sanitised SVG as ' . $stored . '. Active content stripped.';
                } else {
                    $msg = 'Could not write sanitised file.';
                }
            }
        }
    }
}

layout_open('SVG upload (defended)');
?>
<h1>SVG upload (defended)</h1>
<p>libmagic sniff, then a hand-rolled XML scrub that strips <code>&lt;script&gt;</code>, <code>&lt;foreignObject&gt;</code>, every <code>on*</code> handler, and <code>javascript:</code> URIs. Served with <code>Content-Disposition: attachment</code> + <code>X-Content-Type-Options: nosniff</code>. The production-grade equivalent is <a href="https://github.com/darylldoyle/svg-sanitizer">enshrined/svg-sanitize</a>.</p>

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

<?php layout_close(); ?>
