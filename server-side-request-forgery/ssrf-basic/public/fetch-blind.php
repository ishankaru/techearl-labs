<?php
require __DIR__ . '/shared/layout.php';

/*
 * Blind SSRF endpoint. The fetcher runs server-side but the response body
 * is discarded; the client only ever sees "OK" or "Timeout". This is the
 * realistic shape for back-office "send a webhook", "warm a cache", "verify a
 * URL" features that swallow the response.
 *
 * The only oracle the attacker has is the response time. A reachable host
 * returns "OK" within milliseconds. A non-routable address (RFC 5737, or a
 * dropped private range) hangs until the 5-second curl timeout fires and
 * returns "Timeout". Sweeping through internal IP ranges and ports against
 * this oracle is how blind SSRF gets mapped.
 *
 * curl is used here rather than file_get_contents because curl gives us a
 * real connect+read timeout knob. file_get_contents respects
 * default_socket_timeout but the semantics are messier.
 */

const TIMEOUT_SECONDS = 5;

$url = $_GET['url'] ?? '';
$result = null;
$elapsed_ms = null;

if ($url !== '') {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => TIMEOUT_SECONDS,
        CURLOPT_TIMEOUT => TIMEOUT_SECONDS,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_NOBODY => false,
    ]);
    $start = microtime(true);
    $body = curl_exec($ch);
    $elapsed_ms = (int)round((microtime(true) - $start) * 1000);
    $errno = curl_errno($ch);
    curl_close($ch);

    // Response body is intentionally discarded. The endpoint reveals nothing
    // about the upstream beyond "did the request complete or did it time out".
    if ($errno === CURLE_OPERATION_TIMEOUTED || $errno === 28) {
        $result = 'Timeout';
    } elseif ($body === false) {
        $result = 'Timeout';
    } else {
        $result = 'OK';
    }
}

layout_open('Blind Demo');
?>
<h1>URL preview (blind)</h1>
<p>The server fetches the URL but returns only the outcome, never the response body. Timeout is <?= (int)TIMEOUT_SECONDS ?>&nbsp;seconds.</p>

<form method="get" action="/fetch-blind.php">
  <label for="url">URL to fetch</label>
  <input type="text" id="url" name="url" value="<?= h($url) ?>" placeholder="http://ssrf-basic-internal/">
  <button type="submit">Fetch</button>
</form>

<?php if ($url !== ''): ?>
  <h2>Result</h2>
  <?php if ($result === 'OK'): ?>
    <div class="ok">OK (<?= (int)$elapsed_ms ?>&nbsp;ms)</div>
  <?php else: ?>
    <div class="alert">Timeout (<?= (int)$elapsed_ms ?>&nbsp;ms)</div>
  <?php endif; ?>
<?php endif; ?>

<?php
layout_close();
