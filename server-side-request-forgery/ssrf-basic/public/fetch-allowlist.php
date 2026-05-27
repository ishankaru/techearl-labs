<?php
require __DIR__ . '/shared/layout.php';

/*
 * Naive-allowlist SSRF endpoint. The "validation" extracts the host with
 * parse_url() and compares against a hard-coded allowlist. This is the
 * realistic broken pattern, and the bypass is the userinfo trick:
 *
 *     http://example.com@ssrf-basic-internal/
 *
 * parse_url() returns 'example.com' as the host (because the part before '@'
 * is the userinfo component, and parse_url surfaces only the segment
 * immediately after it as PHP_URL_HOST when the URL is written that way).
 * file_get_contents and curl, however, send the request to the real
 * authority, which is ssrf-basic-internal. Allowlist passes; fetch lands on
 * the internal target.
 *
 * Other realistic bypasses that also work against this code:
 *   - DNS rebinding (host resolves to allowed IP on first lookup, internal IP
 *     on second). Not demonstrated in-lab, requires an external DNS server.
 *   - Open redirect on the allowlisted host (allowlist sees example.com,
 *     fetcher follows the 302 to anywhere). Try pointing at any open redirect
 *     you control.
 */

const ALLOWED_HOSTS = ['example.com', 'api.example.com'];

$url = $_GET['url'] ?? '';
$body = null;
$error = null;
$rejected = false;

if ($url !== '') {
    $parsed = parse_url($url);
    $host = $parsed['host'] ?? '';
    if (!in_array(strtolower($host), ALLOWED_HOSTS, true)) {
        $rejected = true;
    } else {
        $body = @file_get_contents($url);
        if ($body === false) {
            $error = error_get_last()['message'] ?? 'fetch failed';
        }
    }
}

layout_open('Allowlist Demo');
?>
<h1>URL preview (allowlist)</h1>
<p>Same fetcher as <a href="/fetch.php">/fetch.php</a>, but the URL host is checked against an allowlist before fetching. Allowed hosts: <code>example.com</code>, <code>api.example.com</code>.</p>

<form method="get" action="/fetch-allowlist.php">
  <label for="url">URL to fetch</label>
  <input type="text" id="url" name="url" value="<?= h($url) ?>" placeholder="https://example.com/">
  <button type="submit">Fetch</button>
</form>

<?php if ($url !== ''): ?>
  <h2>Result</h2>
  <?php if ($rejected): ?>
    <div class="alert">Host not in allowlist. Parsed host: <code><?= h(parse_url($url, PHP_URL_HOST) ?? '') ?></code></div>
  <?php elseif ($error !== null): ?>
    <div class="alert"><?= h($error) ?></div>
  <?php else: ?>
    <div class="ok">Allowlist passed. Response body below.</div>
    <pre><?= h((string)$body) ?></pre>
  <?php endif; ?>
<?php endif; ?>

<?php
layout_close();
