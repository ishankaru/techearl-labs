<?php
/**
 * Vulnerable cookie-deserialization endpoint.
 *
 * Pattern: the app stores a serialized "session state" object in a
 * cookie called `state`, base64-encoded so it survives Set-Cookie
 * transport. On every request the server decodes the cookie and
 * unserializes it without filtering, which is exactly the shape of the
 * old Rails 3 Marshal cookie store, ASP.NET ViewState without MAC, and
 * a thousand PHP-framework session backends from the pre-2018 era.
 */

require_once '/var/www/src/Logger.php';

header('Content-Type: text/plain');

if (!isset($_COOKIE['state'])) {
    // Mint a fresh, benign cookie so a first-time visitor sees the
    // shape of the legitimate payload.
    $fresh = ['user' => 'guest', 'visits' => 1];
    setcookie('state', base64_encode(serialize($fresh)), 0, '/');
    echo "no state cookie present; minted a fresh one. reload to see it deserialize.\n";
    exit;
}

$raw = base64_decode($_COOKIE['state'], true);
if ($raw === false) {
    echo "cookie was not valid base64\n";
    exit;
}

// THE BUG. No `allowed_classes` option, no HMAC, no nothing.
$state = unserialize($raw);

// The "application logic" doesn't even have to run — the gadget chain
// already fired during the unserialize call above (via __wakeup) and
// will fire again at shutdown (via __destruct).
echo "deserialized state object of type: " . (is_object($state) ? get_class($state) : gettype($state)) . "\n";
if (is_array($state)) {
    echo "fields: " . json_encode($state) . "\n";
}
