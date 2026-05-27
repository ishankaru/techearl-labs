<?php
require __DIR__ . '/shared/layout.php';

layout_open('lfi-basic');
?>
<h1>lfi-basic: Local File Inclusion playground</h1>
<p><small class="note">Deliberately vulnerable lab. See README.</small></p>

<div class="panel">
  <h2>/view.php</h2>
  <p>Includes a page from <code>pages/</code>, appending <code>.php</code> to the requested name.</p>
  <p>Intended use: <a href="/view.php?page=pages/about">/view.php?page=pages/about</a>, <a href="/view.php?page=pages/contact">/view.php?page=pages/contact</a></p>
  <p>The fact that <code>.php</code> is appended blocks the textbook <code>/etc/passwd</code> read, but does not block the <code>php://filter</code> source-disclosure trick or the <code>php://input</code> RCE chain (both wrappers ignore any trailing suffix).</p>
</div>

<div class="panel">
  <h2>/view-raw.php</h2>
  <p>Includes the requested file with no suffix at all. The textbook traversal read works directly:</p>
  <pre>curl 'http://localhost:8084/view-raw.php?page=../../../../etc/passwd'</pre>
</div>

<?php layout_close();
