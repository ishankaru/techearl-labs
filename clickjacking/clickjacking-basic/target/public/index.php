<?php
require_once __DIR__ . '/_layout.php';
$u = current_user();
layout_open('Home');
echo '<h1>clickjacking-target</h1>';
echo '<p class="note">Deliberately framable application. The /dashboard route ships without X-Frame-Options or frame-ancestors so it can be embedded by the attacker container on a different origin. The /protected/dashboard route is the defended reference.</p>';
if ($u) {
    echo '<p>Signed in as <strong>' . h($u) . '</strong>. <a href="/dashboard">Go to dashboard</a>.</p>';
} else {
    echo '<p><a href="/login">Sign in</a> to set a session, then visit /dashboard.</p>';
}
layout_close();
