<?php
require __DIR__ . '/../shared/layout.php';

/*
 * DEFENDED slow-body sibling. Read this carefully — the defence model is
 * subtle and the lab is explicit about which layer fixes what.
 *
 * Under mod_php (which this lab uses), Apache fully buffers the request
 * body BEFORE invoking the PHP script. That means a PHP-level
 * stream_set_timeout on php://input cannot actually fire on a slow client:
 * by the time PHP runs, the body is already in memory. The right fix lives
 * at the Apache layer, in mod_reqtimeout:
 *
 *     RequestReadTimeout header=20-40,MinRate=500 body=10,MinRate=500
 *
 * That directive enforces a minimum byte rate from the client during both
 * header and body read. Apache's php:8.2-apache base image ships with this
 * enabled by default. THIS LAB DELIBERATELY DISABLES IT in apache-mods.conf
 * so the vulnerable /slow.php endpoint actually demonstrates worker
 * starvation. In production: leave mod_reqtimeout on, set the values above
 * (or tighter), and additionally configure the same body-read deadline at
 * any reverse proxy in front of Apache (nginx client_body_timeout, Envoy
 * route timeout, Cloudflare slow-POST mitigation).
 *
 * What this endpoint enforces at the PHP layer is the second line of
 * defence that matters on PHP-FPM / Swoole / ReactPHP / any non-mod_php
 * stack that streams php://input lazily:
 *
 *   1. Body-size cap (1 MB). Rejects oversize uploads on every stack.
 *   2. Wall-clock execution deadline via max_execution_time. The vulnerable
 *      endpoint has it at 60s; this one shaves it to 8s so the page can
 *      bound its own work even when Apache hands over a fully-buffered body.
 *
 * Together with the Apache directive above, that is the full defence.
 */

set_time_limit(8);

const MAX_BODY_BYTES = 1 * 1024 * 1024;

$result = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t0 = microtime(true);

    $content_length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($content_length > MAX_BODY_BYTES) {
        $err = sprintf('Refused: Content-Length %d exceeds cap of %d bytes.', $content_length, MAX_BODY_BYTES);
    } else {
        $body = file_get_contents('php://input');
        if (strlen($body) > MAX_BODY_BYTES) {
            $err = sprintf('Refused: body length %d exceeds cap of %d bytes.', strlen($body), MAX_BODY_BYTES);
        } else {
            $elapsed_ms = (microtime(true) - $t0) * 1000;
            $result = [
                'bytes_received' => strlen($body),
                'elapsed_ms' => round($elapsed_ms, 2),
                'worker_pid' => getmypid(),
            ];
        }
    }
}

layout_open('slow body (defended)');
?>
<h1>/defended/slow.php — defended slow-body sibling</h1>
<p><small class="note">Body cap <?= number_format(MAX_BODY_BYTES) ?> bytes, PHP execution deadline 8s. The real slow-body defence lives one layer up — see the note below.</small></p>

<h2>Where the defence actually lives</h2>
<p>Under <code>mod_php</code> Apache buffers the body before invoking PHP, so a PHP-level <code>stream_set_timeout</code> on <code>php://input</code> never fires. The slow-body fix has to be in Apache:</p>
<pre>RequestReadTimeout header=20-40,MinRate=500 body=10,MinRate=500</pre>
<p>That directive is enabled by default in the <code>php:8.2-apache</code> image. This lab disables it (see <code>apache-mods.conf</code>) so the vulnerable <code>/slow.php</code> endpoint can demonstrate worker starvation. In production, keep it on and set a matching <code>client_body_timeout</code> at any reverse proxy in front. The PHP-level cap below is the second line of defence for non-mod_php stacks (PHP-FPM, Swoole, ReactPHP) where <code>php://input</code> does stream.</p>

<h2>Try it</h2>
<pre># Normal upload, should succeed instantly.
yes a | head -c 4096 | curl -sS \
    -H 'Content-Type: application/octet-stream' \
    --data-binary @- http://localhost:8092/defended/slow.php

# Oversize upload, should refuse on Content-Length alone.
yes a | head -c 2000000 | curl -sS \
    -H 'Content-Type: application/octet-stream' \
    --data-binary @- http://localhost:8092/defended/slow.php</pre>

<?php if ($err): ?>
    <div class="ok"><?= h($err) ?></div>
<?php elseif ($result): ?>
    <h2>Result</h2>
    <pre><?= h(json_encode($result, JSON_PRETTY_PRINT)) ?></pre>
<?php endif; ?>

<?php layout_close();
