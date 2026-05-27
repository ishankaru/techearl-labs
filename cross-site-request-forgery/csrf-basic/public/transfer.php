<?php
// VULNERABLE: classic form-CSRF target. No token. No Origin/Referer check.
// State change on a POST that only requires the session cookie.

require __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain');
    echo "POST to=<name>&amount=<int>\n";
    exit;
}

$state = require_login();

$to     = $_POST['to'] ?? '';
$amount = (int)($_POST['amount'] ?? 0);

if ($to === '' || $amount <= 0 || $amount > (int)$state['balance']) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo "Bad transfer: to=$to amount=$amount balance=" . (int)$state['balance'] . "\n";
    exit;
}

$state['balance'] = (int)$state['balance'] - $amount;
append_log($state, "transfer.php: -\$$amount to $to (no csrf check, no origin check)");
save_state($state);

header('Content-Type: text/plain');
echo "OK: transferred \$$amount to $to. New balance: \$" . (int)$state['balance'] . ".\n";
