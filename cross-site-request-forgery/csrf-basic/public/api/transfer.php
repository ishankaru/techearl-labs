<?php
// VULNERABLE: JSON-ish endpoint that calls json_decode on the raw body
// regardless of Content-Type. An attacker can send a text/plain body shaped
// as JSON and skip the CORS preflight that application/json would require.

require __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'POST only']);
    exit;
}

$state = require_login();

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);   // does NOT check Content-Type. This is the bug.

if (!is_array($body)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'invalid JSON', 'received' => $raw]);
    exit;
}

$to     = $body['to']     ?? '';
$amount = (int)($body['amount'] ?? 0);

if ($to === '' || $amount <= 0 || $amount > (int)$state['balance']) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'bad transfer', 'balance' => (int)$state['balance']]);
    exit;
}

$state['balance'] = (int)$state['balance'] - $amount;
$ct = $_SERVER['CONTENT_TYPE'] ?? '(none)';
append_log($state, "api/transfer.php: -\$$amount to $to (Content-Type: $ct)");
save_state($state);

header('Content-Type: application/json');
echo json_encode(['ok' => true, 'balance' => (int)$state['balance'], 'received_content_type' => $ct]);
