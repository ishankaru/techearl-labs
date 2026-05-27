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

<?php layout_close(); ?>
