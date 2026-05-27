<?php
/*
 * VULNERABLE: include() of a file under a user-controlled name. The
 * directory is constrained to /uploads/imgproc/ via realpath() and basename(),
 * which closes path traversal but not the polyglot path: if the file is a
 * real JPEG with a <?php ... ?> block embedded in EXIF, PHP's include()
 * scans for the opening tag and executes whatever comes next. The image
 * bytes around the payload are emitted as raw output and ignored by the
 * interpreter.
 *
 * This is the classic LFI-meets-upload chain that turns "we only accept
 * images" into RCE. The defence is to never include() user-controlled
 * paths, full stop. Use a database id and an explicit allowlist of
 * include targets, or serve uploads as static bytes through readfile()
 * with Content-Disposition: attachment and X-Content-Type-Options: nosniff.
 */
require_once __DIR__ . '/shared/layout.php';

$base    = realpath(__DIR__ . '/uploads/imgproc');
$img     = isset($_GET['img']) ? basename($_GET['img']) : '';
$target  = $base . '/' . $img;
$real    = $img !== '' ? realpath($target) : false;

layout_open('Image viewer');
?>
<h1>Image viewer</h1>
<p>Renders an image from <code>/uploads/imgproc/</code> by <code>include()</code>-ing the file. Pass <code>?img=&lt;filename&gt;</code>.</p>

<?php if ($img === ''): ?>
  <p><small class="note">No <code>img</code> parameter supplied.</small></p>
<?php elseif ($real === false || strpos($real, $base) !== 0): ?>
  <div class="alert">File not found or outside the uploads directory.</div>
<?php else: ?>
  <p><small class="note">Including <code><?php echo h($real); ?></code> via PHP include().</small></p>
  <pre style="background:#0f172a;color:#e2e8f0;padding:.75rem;border-radius:.25rem;font-size:12px;overflow:auto;max-height:300px">
<?php
    // Bonkers on purpose. The point is the include() trust failure.
    include $real;
?>
</pre>
<?php endif; ?>

<?php layout_close(); ?>
