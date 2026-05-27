<?php
/*
 * Login + logout for the xss-basic lab.
 *
 * Login uses a parameterised SELECT and password_verify(). SQL injection is
 * NOT the target in this lab, the data layer is deliberately safe so the
 * article's focus stays on the rendering-layer bugs.
 *
 * On success we mint a random 64-char hex session id, store it in the
 * sessions table, and set it as a cookie. Crucially the cookie is set
 * without HttpOnly (see shared/auth.php for the rationale). That is the
 * misconfiguration the cookie-theft chain in the companion article relies
 * on: any of the three XSS sinks can read document.cookie and exfiltrate
 * the session id to an attacker-controlled endpoint.
 */

require_once __DIR__ . '/shared/db.php';
$conn = db();

// Logout
if (isset($_GET['logout'])) {
    $sid = $_COOKIE['session_id'] ?? '';
    if ($sid !== '') {
        $stmt = $conn->prepare('DELETE FROM sessions WHERE id = ?');
        $stmt->bind_param('s', $sid);
        $stmt->execute();
    }
    session_cookie_clear();
    header('Location: /login.php');
    exit;
}

$err = null;
$user = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare('SELECT id, username, password, email, is_admin FROM users WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row && password_verify($password, $row['password'])) {
        $sid = bin2hex(random_bytes(32));
        $ins = $conn->prepare('INSERT INTO sessions (id, user_id) VALUES (?, ?)');
        $ins->bind_param('si', $sid, $row['id']);
        $ins->execute();
        session_cookie_set($sid);
        header('Location: /');
        exit;
    }
    $err = 'Invalid username or password.';
}

layout_open('Login');
echo '<h1>Sign in</h1>';
if ($err) echo '<div class="alert">' . h($err) . '</div>';

echo '<form method="post">';
echo '<label>Username</label>';
echo '<input type="text" name="username" value="' . h($_POST['username'] ?? '') . '" autofocus>';
echo '<label>Password</label>';
echo '<input type="password" name="password">';
echo '<button type="submit">Sign in</button>';
echo '</form>';

echo '<p style="margin-top:1.5rem"><small class="note">Demo accounts: admin/admin123, alice/alice123, bob/bob123.</small></p>';

layout_close();
