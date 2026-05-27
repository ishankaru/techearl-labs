<?php
require __DIR__ . '/shared/layout.php';

layout_open('upload-basic');
?>
<h1>upload-basic — file upload validation flaws</h1>
<p><small class="note">Deliberately vulnerable lab. See README.</small></p>

<p>Four upload endpoints, each implementing a different broken validation flavour. Every endpoint writes into a directory that Apache will happily serve, so any uploaded <code>.php</code> file is one request away from execution.</p>

<div class="card">
  <h2><a href="/upload-naive.php">/upload-naive.php</a></h2>
  <p>No validation at all. Accepts anything, stores under the original filename.</p>
  <p><small class="note">Lands in <code>/uploads/naive/</code>.</small></p>
</div>

<div class="card">
  <h2><a href="/upload-blacklist.php">/upload-blacklist.php</a></h2>
  <p>Blocks a hard-coded list of extensions (<code>php</code>, <code>phtml</code>, <code>php3</code>, <code>php4</code>). Misses the case-insensitive variants and the less-common executable extensions (<code>phP</code>, <code>pht</code>, <code>phar</code>).</p>
  <p><small class="note">Lands in <code>/uploads/blacklist/</code>.</small></p>
</div>

<div class="card">
  <h2><a href="/upload-mime.php">/upload-mime.php</a></h2>
  <p>Trusts the client-supplied <code>Content-Type</code> in the multipart part. Anything that claims <code>image/jpeg</code> is accepted regardless of contents.</p>
  <p><small class="note">Lands in <code>/uploads/mime/</code>.</small></p>
</div>

<div class="card">
  <h2><a href="/upload-double-ext.php">/upload-double-ext.php</a></h2>
  <p>Checks the trailing extension only, but the upload directory is configured with the unsafe <code>AddHandler</code> directive, so any filename containing <code>.php</code> anywhere executes.</p>
  <p><small class="note">Lands in <code>/uploads/double-ext/</code>.</small></p>
</div>

<div class="card">
  <h2><a href="/upload-imgproc.php">/upload-imgproc.php</a></h2>
  <p>Magic-byte check via libmagic (must report <code>image/*</code>), then hands the file to ImageMagick's <code>identify</code> binary. A real JPEG with PHP in EXIF metadata passes the magic-byte check; an ImageMagick MVG/MSL payload can reach <code>identify</code>; the stored file can be triggered via <code>/view.php</code>.</p>
  <p><small class="note">Lands in <code>/uploads/imgproc/</code>.</small></p>
</div>

<div class="card">
  <h2><a href="/view.php">/view.php</a></h2>
  <p>Renders an uploaded image by <code>include()</code>-ing it. The trust failure: PHP scans the included file for <code>&lt;?php</code> and executes whatever it finds, so an EXIF-embedded payload in a real JPEG becomes RCE.</p>
  <p><small class="note">Pass <code>?img=&lt;filename&gt;</code>. Constrained to <code>/uploads/imgproc/</code>.</small></p>
</div>

<div class="card">
  <h2><a href="/upload-strict.php">/upload-strict.php</a> (defended reference)</h2>
  <p>Extension allowlist plus libmagic MIME check plus full GD re-encode plus random filename plus a non-executing upload directory. Any polyglot payload is stripped by the re-encode step. Use this as the working pattern to copy.</p>
  <p><small class="note">Lands in <code>/uploads/strict/</code>.</small></p>
</div>

<?php layout_close(); ?>
