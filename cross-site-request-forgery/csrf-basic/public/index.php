<?php
require __DIR__ . '/_bootstrap.php';
$state = load_state();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>csrf-basic lab</title>
<style>
body { font-family: system-ui, sans-serif; max-width: 720px; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
code, pre { background: #f4f4f4; padding: 2px 4px; border-radius: 3px; }
pre { padding: 0.8rem; overflow-x: auto; }
table { border-collapse: collapse; margin: 1rem 0; width: 100%; }
th, td { border: 1px solid #ddd; padding: 6px 10px; text-align: left; }
.bad { color: #b30000; }
.good { color: #006400; }
</style>
</head>
<body>
<h1>csrf-basic</h1>
<p>Toy "bank" application used by the <a href="https://techearl.com/cross-site-request-forgery">CSRF deep dive</a>.
Session cookies are issued with <code>SameSite=Lax</code> (the realistic 2026 default). Bind to 127.0.0.1 only.</p>

<h2>State</h2>
<p>Logged in: <strong><?= empty($state['logged_in']) ? 'no' : htmlspecialchars($state['user']) ?></strong>,
balance: <strong>$<?= (int)($state['balance'] ?? 0) ?></strong>,
email: <strong><?= htmlspecialchars($state['email'] ?? '(unset)') ?></strong></p>

<h2>Endpoints</h2>
<table>
<tr><th>Path</th><th>Method</th><th>Defence</th></tr>
<tr><td><code>/login.php</code></td><td>POST</td><td>n/a; sets session cookie</td></tr>
<tr><td><code>/dashboard.php</code></td><td>GET</td><td>n/a; shows balance + log</td></tr>
<tr><td><code>/transfer.php</code></td><td>POST</td><td><span class="bad">vulnerable</span>, no token, no Origin</td></tr>
<tr><td><code>/api/transfer.php</code></td><td>POST JSON</td><td><span class="bad">vulnerable</span>, accepts Content-Type: text/plain</td></tr>
<tr><td><code>/email/change.php</code></td><td>POST</td><td><span class="bad">broken</span>, only checks token when supplied</td></tr>
<tr><td><code>/strict/transfer.php</code></td><td>POST</td><td><span class="good">defended</span>: Origin + token + SameSite=Strict</td></tr>
<tr><td><code>/attacker/csrf.html</code></td><td>GET</td><td>attacker landing page (served from the same host for demo simplicity; treat it as another origin)</td></tr>
</table>

<h2>Login</h2>
<form method="POST" action="/login.php">
  <input name="username" placeholder="alice" required>
  <input name="password" type="password" placeholder="alice123" required>
  <button type="submit">Login</button>
</form>
<p>Seed credentials: <code>alice / alice123</code>.</p>

<h2>Log</h2>
<pre><?php
foreach (($state['log'] ?? []) as $line) {
    echo htmlspecialchars($line) . "\n";
}
?></pre>

</body>
</html>
