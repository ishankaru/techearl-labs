<?php
require __DIR__ . '/_bootstrap.php';
$state = require_login();
$token = csrf_token($state);

header('Content-Type: text/plain');
echo "user:    " . $state['user'] . "\n";
echo "balance: \$" . (int)$state['balance'] . "\n";
echo "email:   " . $state['email'] . "\n";
echo "csrf:    " . $token . "\n";
echo "\nrecent activity:\n";
foreach ($state['log'] as $line) {
    echo "  " . $line . "\n";
}
