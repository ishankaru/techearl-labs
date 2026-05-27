<?php
/*
 * Mock internal admin panel. In a real environment this would sit behind a
 * VPN, on a private VLAN, or on localhost-only on the application host.
 * Anyone who can reach this URL is, by the operator's threat model,
 * authenticated. That is the assumption SSRF breaks.
 */
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Internal admin panel</title>
</head>
<body>
  <h1>Welcome to the internal admin panel</h1>
  <p>This service is intended for internal traffic only. If you are seeing this from outside the trusted network, something is wrong.</p>

  <h2>Internal endpoints</h2>
  <ul>
    <li><a href="/users">/users</a> — user directory</li>
    <li><a href="/reports">/reports</a> — finance reports</li>
    <li><a href="/deploy">/deploy</a> — deployment hooks</li>
    <li><a href="/secrets">/secrets</a> — secrets manager UI</li>
  </ul>

  <hr>
  <small>ssrf-basic-internal / lab target</small>
</body>
</html>
