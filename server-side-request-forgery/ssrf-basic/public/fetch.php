<?php
require __DIR__ . '/shared/layout.php';

/*
 * Basic SSRF endpoint. The vulnerability is in the very first call:
 * file_get_contents($_GET['url']) takes a fully attacker-controlled URL and
 * fetches it server-side, with no scheme check, no host check, no DNS
 * resolution check, no redirect cap. This is the textbook shape.
 *
 * The body is rendered inside <pre> via h() so the response itself cannot
 * inject HTML / JS into the lab page. The SSRF is in the fetcher; the
 * renderer is intentionally clean.
 */

$url = $_GET['url'] ?? '';
$body = null;
$error = null;

if ($url !== '') {
    // Suppress warnings so a failed fetch surfaces as a tidy message rather
    // than a PHP warning dumped above the layout. The fetch itself is still
    // unsafe, that is the whole point.
    $body = @file_get_contents($url);
    if ($body === false) {
        $error = error_get_last()['message'] ?? 'fetch failed';
    }
}

layout_open('URL Preview');
?>
<h1>URL preview</h1>
<p>Paste a URL and the server fetches it for you. Useful for previewing links before sharing them, importing remote JSON, testing webhooks, and so on.</p>

<form method="get" action="/fetch.php">
  <label for="url">URL to fetch</label>
  <input type="text" id="url" name="url" value="<?= h($url) ?>" placeholder="https://example.com/">
  <button type="submit">Fetch</button>
</form>

<?php if ($url !== ''): ?>
  <h2>Response from <code><?= h($url) ?></code></h2>
  <?php if ($error !== null): ?>
    <div class="alert"><?= h($error) ?></div>
  <?php else: ?>
    <pre><?= h((string)$body) ?></pre>
  <?php endif; ?>
<?php endif; ?>

<?php
layout_close();
