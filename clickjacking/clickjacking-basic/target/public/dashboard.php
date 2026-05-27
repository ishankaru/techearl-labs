<?php
/*
 * VULNERABLE dashboard. No X-Frame-Options. No CSP frame-ancestors. Any
 * other origin can embed this page in an iframe and route clicks to the
 * "Delete account" button. PHP does not set these headers by default and
 * we do not set them here, so the response goes out bare.
 *
 * The /protected/dashboard.php variant is the same page with the two
 * headers added; compare the two with curl -I.
 */
require_once __DIR__ . '/_layout.php';
start_session();
$u = current_user();
if (!$u) {
    header('Location: /login');
    exit;
}

layout_open('Dashboard');
echo '<h1>Dashboard</h1>';
echo '<p>Signed in as <strong>' . h($u) . '</strong>.</p>';
if (!empty($_SESSION['deleted'])) {
    echo '<p class="banner">Your account was just deleted via /confirm. (Lab marker: the framed click landed.)</p>';
}
echo '<p class="note">This route does NOT set X-Frame-Options or CSP frame-ancestors. It is framable from any origin, which is exactly the bug.</p>';
echo '<form method="post" action="/confirm">';
echo '  <p>Danger zone:</p>';
echo '  <button>Delete account</button>';
echo '</form>';
layout_close();
