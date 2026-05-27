<?php
require __DIR__ . '/shared/layout.php';

/*
 * VULNERABLE: accepts a gzipped request body and decompresses it without any
 * size guard. A 1 KB payload of zero bytes expands to ~1 GB; the PHP process
 * happily reads the entire decompressed string into memory.
 *
 * `memory_limit = 256M` in php.ini will kill the request before it actually
 * eats a host gigabyte, but the article point stands: the decompression cost
 * is real, the request is tiny, and a real server with no memory_limit (or a
 * higher one) gets crushed. The Docker container has mem_limit=512m as a
 * second safety net.
 */

$result = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $compressed_bytes = strlen($raw);

    // NO size cap. NO streaming. Read the entire decompressed string into RAM.
    $t0 = microtime(true);
    $decoded = @gzdecode($raw);
    $elapsed_ms = (microtime(true) - $t0) * 1000;

    if ($decoded === false) {
        $err = 'gzdecode failed: input is not a valid gzip stream';
    } else {
        $result = [
            'compressed_bytes' => $compressed_bytes,
            'decompressed_bytes' => strlen($decoded),
            'ratio' => $compressed_bytes > 0
                ? round(strlen($decoded) / $compressed_bytes, 1)
                : 0,
            'elapsed_ms' => round($elapsed_ms, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 1),
        ];
    }
}

layout_open('upload (decompression bomb)');
?>
<h1>/upload.php — vulnerable to a decompression bomb</h1>
<p><small class="note">Reads <code>php://input</code>, runs <code>gzdecode</code>, no size guard. A 1 KB gzip of zeroes expands to ~1 GB.</small></p>

<h2>Build a bomb (local lab only)</h2>
<pre># 1 GB of zero bytes, compressed. Result is ~1 MB on disk.
dd if=/dev/zero bs=1M count=1024 status=none | gzip -9 > /tmp/bomb.gz
ls -lh /tmp/bomb.gz

# POST it.
curl -sS --data-binary @/tmp/bomb.gz http://localhost:8092/upload.php</pre>

<?php if ($err): ?>
    <div class="alert"><?= h($err) ?></div>
<?php elseif ($result): ?>
    <h2>Result</h2>
    <pre><?= h(json_encode($result, JSON_PRETTY_PRINT)) ?></pre>
<?php endif; ?>

<p>Compare to <a href="/defended/upload.php">/defended/upload.php</a>, which caps the decompressed size at 10 MB and aborts the moment the inflater goes past that.</p>

<?php layout_close();
