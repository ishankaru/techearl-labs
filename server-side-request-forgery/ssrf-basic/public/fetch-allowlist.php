<?php
require __DIR__ . '/shared/layout.php';

/*
 * Naive-allowlist SSRF endpoint. The "validation" is a substring check: if
 * the URL string contains 'example.com' anywhere, the fetch is allowed. This
 * is the textbook broken-allowlist pattern, the kind of code reviewers find
 * grepping for "allowlist" / "whitelist" in PHP apps every day.
 *
 * Bypass (works in-lab):
 *
 *     http://ssrf-basic-internal/?fake=example.com
 *
 * strpos() finds 'example.com' in the query string. Allowlist passes.
 * file_get_contents follows the actual authority and fetches
 * ssrf-basic-internal. The "allowed" string never had any positional
 * meaning, the developer just checked it was present somewhere.
 *
 * Other realistic bypasses that also work against substring checks:
 *   - http://example.com.attacker.tld/  (registerable subdomain shape)
 *   - http://attacker.tld/example.com   (path-only)
 *   - http://example.com@attacker.tld/  (userinfo trick; some validators
 *     extract host correctly but the developer here never bothered to
 *     parse, they just grepped).
 */

$url = $_GET['url'] ?? '';
$body = null;
$error = null;
$rejected = false;

if ($url !== '') {
    if (strpos($url, 'example.com') === false) {
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
<p>Same fetcher as <a href="/fetch.php">/fetch.php</a>, but URLs are filtered through a substring check: the URL must contain the string <code>example.com</code> somewhere.</p>

<form method="get" action="/fetch-allowlist.php">
  <label for="url">URL to fetch</label>
  <input type="text" id="url" name="url" value="<?= h($url) ?>" placeholder="https://example.com/">
  <button type="submit">Fetch</button>
</form>

<?php if ($url !== ''): ?>
  <h2>Result</h2>
  <?php if ($rejected): ?>
    <div class="alert">URL does not contain an allowed domain.</div>
  <?php elseif ($error !== null): ?>
    <div class="alert"><?= h($error) ?></div>
  <?php else: ?>
    <div class="ok">Allowlist passed. Response body below.</div>
    <pre><?= h((string)$body) ?></pre>
  <?php endif; ?>
<?php endif; ?>

<?php
layout_close();
