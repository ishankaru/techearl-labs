<?php
/*
 * Serves a previously-uploaded SVG file from /uploads/svg/ with
 * Content-Type: image/svg+xml. This is the route the browser hits to
 * trigger XSS against an SVG uploaded through /upload-svg.php.
 *
 * No Content-Disposition: attachment, no X-Content-Type-Options: nosniff,
 * no sanitisation. The whole point of the lab is to make the active-document
 * behaviour of SVG visible.
 */

$f = $_GET['f'] ?? '';
if ($f === '' || strpos($f, '/') !== false || strpos($f, '..') !== false) {
    http_response_code(400);
    echo "Bad request";
    exit;
}

$path = __DIR__ . '/uploads/svg/' . $f;
if (!is_file($path)) {
    http_response_code(404);
    echo "Not found";
    exit;
}

header('Content-Type: image/svg+xml');
header('Content-Length: ' . filesize($path));
readfile($path);
