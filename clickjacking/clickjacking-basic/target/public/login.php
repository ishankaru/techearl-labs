<?php
/*
 * Trivial login. Any non-empty username sets a session. No password,
 * no user table. The lab is about UI redress, not authentication.
 */
require_once __DIR__ . '/_layout.php';
start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    if ($username !== '') {
        $_SESSION['user'] = $username;
        $_SESSION['deleted'] = false;
        header('Location: /dashboard');
        exit;
    }
}

layout_open('Login');
echo '<h1>Sign in</h1>';
echo '<p class="note">Any username works. The lab uses the session to demonstrate that the framed action runs as the logged-in user.</p>';
echo '<form method="post" action="/login">';
echo '  <label>Username <input name="username" value="alice" autofocus></label> ';
echo '  <button class="btn-ok">Sign in</button>';
echo '</form>';
layout_close();
