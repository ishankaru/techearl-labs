<?php
/*
 * The "prize" action. Marks the session as deleted and bounces back to
 * the dashboard. POST-only, but with no CSRF token and no second
 * confirmation gate, which is exactly the shape of action that gets
 * clickjacked in practice. (The lab keeps the gate to a single click
 * intentionally; the article covers the step-up defence separately.)
 */
require_once __DIR__ . '/_layout.php';
start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'POST only';
    exit;
}

$u = current_user();
if (!$u) {
    header('Location: /login');
    exit;
}

$_SESSION['deleted'] = true;
header('Location: /dashboard');
