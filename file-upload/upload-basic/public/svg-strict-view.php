<?php
/*
 * Defended viewer for sanitised SVGs from /uploads/svg-strict/.
 * Sends Content-Disposition: attachment so the browser downloads instead
 * of rendering as an active document, plus X-Content-Type-Options: nosniff
 * so the browser cannot guess the type. Belt-and-braces: even if the
 * sanitiser missed something, the file does not render as SVG in the
 * page's origin.
 */

$f = $_GET['f'] ?? '';
if ($f === '' || strpos($f, '/') !== false || strpos($f, '..') !== false) {
    http_response_code(400);
    echo "Bad request";
    exit;
}

$path = __DIR__ . '/uploads/svg-strict/' . $f;
if (!is_file($path)) {
    http_response_code(404);
    echo "Not found";
    exit;
}

header('Content-Type: image/svg+xml');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="' . basename($f) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
