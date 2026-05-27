<?php
require __DIR__ . '/shared/layout.php';

layout_open('Home');
?>
<h1>ssrf-basic</h1>
<p><small class="note">Deliberately vulnerable lab. See README.</small></p>

<p>This app exposes three "fetch a URL on the server's behalf" features, each broken in a different realistic way. The companion article walks through every exploit path.</p>

<h2>Endpoints</h2>

<ul>
  <li><a href="/fetch.php"><code>/fetch.php?url=...</code></a> — naive URL preview. Passes the input straight to <code>file_get_contents</code>. No validation at all.</li>
  <li><a href="/fetch-allowlist.php"><code>/fetch-allowlist.php?url=...</code></a> — same fetcher, with a host allowlist that "validates" the URL by checking <code>parse_url($url)['host']</code>. The allowlist is bypassable.</li>
  <li><a href="/fetch-blind.php"><code>/fetch-blind.php?url=...</code></a> — fetcher that returns only "OK" or "Timeout". Response content never reaches the client, so the only oracle is timing.</li>
</ul>

<h2>What lives on the Docker network</h2>

<ul>
  <li><code>http://ssrf-basic-internal/</code> — a second container simulating an internal admin service. Not reachable from your host; reachable from the vulnerable app over the Docker network.</li>
  <li><code>http://ssrf-basic-metadata/latest/meta-data/iam/security-credentials/role-name</code> — a mock AWS IMDSv1 endpoint. Real IMDS lives at <code>169.254.169.254</code>; this lab mocks the same response shape at a Docker-internal address because containers cannot bind to link-local addresses.</li>
</ul>

<?php
layout_close();
