<?php
require __DIR__ . '/../shared/layout.php';

/*
 * DEFENDED: same intent as /upload.php. Two guards.
 *
 *   1. Compressed-size cap on php://input. We never read more than 1 MB of
 *      compressed bytes. Most legitimate gzipped uploads are well under that.
 *   2. Streaming decompression with a hard decompressed-size cap. We inflate
 *      in 8 KB chunks via the `zlib.inflate` filter on a memory stream and
 *      bail the moment we exceed MAX_DECOMPRESSED_BYTES.
 *
 * Crucially we never materialise the full decompressed string in RAM, so the
 * defended path is bounded in both inputs we control: bytes-on-the-wire and
 * bytes-after-inflate.
 */

const MAX_COMPRESSED_BYTES = 1 * 1024 * 1024;     // 1 MB
const MAX_DECOMPRESSED_BYTES = 10 * 1024 * 1024;  // 10 MB

$result = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = fopen('php://input', 'rb');
    $compressed = '';
    while (!feof($in)) {
        $chunk = fread($in, 8192);
        if ($chunk === false) break;
        $compressed .= $chunk;
        if (strlen($compressed) > MAX_COMPRESSED_BYTES) {
            $err = sprintf(
                'Refused: compressed payload exceeds %d bytes (got at least %d).',
                MAX_COMPRESSED_BYTES,
                strlen($compressed)
            );
            break;
        }
    }
    fclose($in);

    if ($err === null) {
        // Skip the 10-byte gzip header so we can feed raw deflate to inflate_init.
        // (gzdecode accepts the header; the streaming inflate API needs raw deflate.)
        $t0 = microtime(true);

        $ctx = inflate_init(ZLIB_ENCODING_GZIP);
        if ($ctx === false) {
            $err = 'inflate_init failed';
        } else {
            $decompressed_bytes = 0;
            $offset = 0;
            $chunk_size = 8192;
            $aborted = false;

            while ($offset < strlen($compressed)) {
                $piece = substr($compressed, $offset, $chunk_size);
                $offset += $chunk_size;

                $out = @inflate_add($ctx, $piece, ZLIB_SYNC_FLUSH);
                if ($out === false) {
                    $err = 'inflate_add failed: malformed gzip stream';
                    $aborted = true;
                    break;
                }
                $decompressed_bytes += strlen($out);
                // Discard the inflated bytes immediately. We never hold the
                // whole decompressed payload in memory.
                unset($out);

                if ($decompressed_bytes > MAX_DECOMPRESSED_BYTES) {
                    $err = sprintf(
                        'Refused: decompressed output exceeds %d bytes (got %d so far).',
                        MAX_DECOMPRESSED_BYTES,
                        $decompressed_bytes
                    );
                    $aborted = true;
                    break;
                }
            }

            $elapsed_ms = (microtime(true) - $t0) * 1000;

            if (!$aborted) {
                $result = [
                    'compressed_bytes' => strlen($compressed),
                    'decompressed_bytes' => $decompressed_bytes,
                    'elapsed_ms' => round($elapsed_ms, 2),
                    'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 1),
                ];
            }
        }
    }
}

layout_open('upload (defended)');
?>
<h1>/defended/upload.php — defended decompression sibling</h1>
<p><small class="note">Compressed cap <?= number_format(MAX_COMPRESSED_BYTES) ?> bytes, decompressed cap <?= number_format(MAX_DECOMPRESSED_BYTES) ?> bytes, streaming inflate.</small></p>

<h2>Try it</h2>
<pre># Same bomb as the vulnerable sibling.
curl -sS --data-binary @/tmp/bomb.gz http://localhost:8092/defended/upload.php</pre>

<?php if ($err): ?>
    <div class="ok"><?= h($err) ?></div>
<?php elseif ($result): ?>
    <h2>Result</h2>
    <pre><?= h(json_encode($result, JSON_PRETTY_PRINT)) ?></pre>
<?php endif; ?>

<p>The defended endpoint refuses the bomb cleanly while inflating less than 10 MB. Memory peak stays flat.</p>

<?php layout_close();
