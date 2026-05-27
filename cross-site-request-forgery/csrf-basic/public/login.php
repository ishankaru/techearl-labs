<?php
require __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain');
    echo "POST username+password to log in.\n";
    exit;
}

$user = $_POST['username'] ?? '';
$pass = $_POST['password'] ?? '';

// One hard-coded account; this is a CSRF lab not an auth lab.
if ($user !== 'alice' || $pass !== 'alice123') {
    http_response_code(401);
    header('Content-Type: text/plain');
    echo "Invalid credentials. Try alice / alice123.\n";
    exit;
}

$state = load_state();
$state['logged_in'] = true;
$state['user']      = 'alice';
$state['balance']   = 1000;
$state['email']     = 'alice@example.com';
append_log($state, "login ok as alice");
save_state($state);

header('Content-Type: text/plain');
echo "Logged in as alice. Balance: \$1000. Email: alice@example.com.\n";
