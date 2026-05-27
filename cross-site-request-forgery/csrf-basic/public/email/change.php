<?php
// BROKEN-TOKEN-CHECK: the developer added a CSRF token check, but they only
// run the comparison if the request carries a token. Missing token = no check.
// Classic "if (isset($_POST['csrf']))" anti-pattern.

require __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain');
    echo "POST email=<addr>&csrf=<token>\n";
    exit;
}

$state = require_login();
$expected = csrf_token($state);

// THE BUG: token comparison only fires when a token is supplied. An attacker
// who omits the field passes the check by default.
if (isset($_POST['csrf'])) {
    if (!hash_equals($expected, (string)$_POST['csrf'])) {
        http_response_code(403);
        header('Content-Type: text/plain');
        echo "CSRF token mismatch.\n";
        exit;
    }
}

$email = trim((string)($_POST['email'] ?? ''));
if ($email === '') {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo "Bad email.\n";
    exit;
}

$state['email'] = $email;
append_log($state, "email/change.php: email -> $email (token " . (isset($_POST['csrf']) ? 'present' : 'ABSENT, check skipped') . ")");
save_state($state);

header('Content-Type: text/plain');
echo "OK: email updated to $email.\n";
