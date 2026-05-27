<?php
/**
 * Vulnerable POST-body deserialization endpoint.
 *
 * Pattern: an internal API expects clients to POST a serialized
 * "request envelope". This shape is more common in B2B integrations
 * and old SOAP-replaced-with-PHP-serialize internal endpoints than in
 * public-facing web apps, but it lives forever once it ships, because
 * every existing client speaks it.
 *
 * Unlike cookie.php this one does NOT base64-decode: it consumes the
 * raw PHP serialization wire format directly off the request body.
 */

require_once '/var/www/src/Logger.php';

header('Content-Type: text/plain');

if (!isset($_POST['data'])) {
    echo "POST a `data` field containing a raw PHP-serialized object.\n";
    echo "example: curl -d 'data=O:6:\"Logger\":2:{...}' http://localhost:8087/post.php\n";
    exit;
}

// THE BUG. Raw unserialize on a request-body field.
$envelope = unserialize($_POST['data']);

echo "deserialized envelope of type: " . (is_object($envelope) ? get_class($envelope) : gettype($envelope)) . "\n";
