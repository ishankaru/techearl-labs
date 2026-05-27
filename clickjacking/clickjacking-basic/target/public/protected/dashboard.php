<?php
/*
 * The defended reference. Same content as /dashboard, but the response
 * carries both modern (CSP frame-ancestors 'none') and legacy
 * (X-Frame-Options: DENY) headers. A browser that supports either one
 * refuses to render this page inside an iframe at all.
 */
require_once __DIR__ . '/../_layout.php';
start_session();

header("Content-Security-Policy: frame-ancestors 'none'");
header('X-Frame-Options: DENY');

$u = current_user();
if (!$u) {
    header('Location: /login');
    exit;
}

layout_open('Protected dashboard');
echo '<h1>Protected dashboard</h1>';
echo '<p>Signed in as <strong>' . h($u) . '</strong>.</p>';
echo '<p class="note">This route DOES set X-Frame-Options: DENY and CSP frame-ancestors \'none\'. The browser refuses to frame it. Try iframing it from the attacker container and the iframe stays empty.</p>';
echo '<form method="post" action="/confirm">';
echo '  <button>Delete account (also POSTs to /confirm)</button>';
echo '</form>';
layout_close();
