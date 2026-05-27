<?php
// DEFENDED: the reference implementation. Three layers:
//   1. Require a per-session anti-CSRF token.
//   2. Validate Origin (falling back to Referer) against the expected host.
//   3. Issue session via SameSite=Strict (see cookie reissue below).

require __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain');
    echo "POST to=<name>&amount=<int>&csrf=<token>\n";
    exit;
}

$state = require_login();
$expected_host = $_SERVER['HTTP_HOST'] ?? '';

// Origin / Referer check.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$ok_origin = false;
if ($origin !== '') {
    $parsed = parse_url($origin);
    if (isset($parsed['host']) && $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '') === $expected_host) {
        $ok_origin = true;
    }
} elseif ($referer !== '') {
    $parsed = parse_url($referer);
    if (isset($parsed['host']) && $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '') === $expected_host) {
        $ok_origin = true;
    }
}
if (!$ok_origin) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "Rejected: Origin/Referer missing or mismatched. expected_host=$expected_host origin=$origin referer=$referer\n";
    exit;
}

// Token check (always, even when not supplied).
$expected = csrf_token($state);
$supplied = (string)($_POST['csrf'] ?? '');
if ($supplied === '' || !hash_equals($expected, $supplied)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "Rejected: CSRF token missing or invalid.\n";
    exit;
}

// Reissue session cookie with SameSite=Strict on this defended endpoint as a
// reminder that the cookie attribute is the third layer. In a real app you set
// this once at session creation; we do it here for visibility.
setcookie(session_name(), session_id(), [
    'expires'  => 0,
    'path'     => '/',
    'httponly' => true,
    'secure'   => false,
    'samesite' => 'Strict',
]);

$to     = $_POST['to'] ?? '';
$amount = (int)($_POST['amount'] ?? 0);
if ($to === '' || $amount <= 0 || $amount > (int)$state['balance']) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo "Bad transfer.\n";
    exit;
}

$state['balance'] = (int)$state['balance'] - $amount;
append_log($state, "strict/transfer.php: -\$$amount to $to (token+origin verified)");
save_state($state);

header('Content-Type: text/plain');
echo "OK: transferred \$$amount to $to. New balance: \$" . (int)$state['balance'] . ".\n";
