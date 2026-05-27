<?php
require __DIR__ . '/shared/layout.php';

layout_open('Home');
?>
<h1>xxe-basic: XML External Entity lab</h1>
<p><small class="note">Deliberately vulnerable lab. See README.</small></p>

<p>This app exposes two XML-parsing endpoints. Both accept a raw XML body on <code>POST</code> and both wire up <code>DOMDocument</code> with the unsafe libxml flag combination that allows external entities, parameter entities, and DTD loading.</p>

<h2>Endpoints</h2>

<h3><code>POST /import.php</code> (in-band parser)</h3>
<p>Parses bookmark XML and echoes each <code>&lt;name&gt;</code> back into the response. Any entity expansion that resolves before output ends up reflected.</p>
<pre><code>curl -s -X POST --data-binary @payload.xml \
  -H 'Content-Type: application/xml' \
  http://localhost:8086/import.php</code></pre>

<h3><code>POST /upload-blind.php</code> (blind parser)</h3>
<p>Same parser configuration, but the response is just <code>OK</code> or <code>Error</code>. No parsed content leaks back. Demonstrates blind XXE via parameter entities and an out-of-band DTD fetched from <code>xxe-basic-collab</code>.</p>
<pre><code>curl -s -X POST --data-binary @blind.xml \
  -H 'Content-Type: application/xml' \
  http://localhost:8086/upload-blind.php</code></pre>

<h2>Collaborator</h2>
<p>The companion <code>xxe-basic-collab</code> container serves <code>http://xxe-basic-collab/evil.dtd</code> on the internal Docker network and logs every incoming request to stdout. Tail it with:</p>
<pre><code>docker compose logs -f xxe-basic-collab</code></pre>
<p>The blind-XXE payload below causes the lab container to issue an HTTP request to the collaborator with the contents of <code>/etc/hostname</code> in the URL path. The exfiltrated bytes show up in the collab logs.</p>

<?php
layout_close();
