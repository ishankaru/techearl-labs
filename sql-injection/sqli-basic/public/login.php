<?php
/*
 * VULNERABLE: both fields concatenated into a SELECT.
 *
 * The textbook login-bypass case:
 *   username = anything' OR '1'='1
 *   password = anything' OR '1'='1
 * Or even just `' OR '1'='1' -- ` in username to bypass without touching
 * the password slot.
 *
 * For realism, the password column stores bcrypt hashes (see seed.sql) and
 * normal logins would call password_verify(). The injection short-circuits
 * that path entirely because the WHERE clause matches a row regardless of
 * what password was supplied.
 */

require_once __DIR__ . '/shared/db.php';
$conn = db();

$err = null;
$user = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $sql = "SELECT id, username, email, is_admin FROM users
            WHERE username = '$username' AND password = '$password'";
    $result = $conn->query($sql);

    if ($result === false) {
        $err = 'DB error: ' . $conn->error;
    } elseif ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
    } else {
        $err = 'Invalid username or password.';
    }
}

layout_open('Login');
echo '<h1>Login</h1>';

if ($user) {
    echo '<div class="alert ok">Welcome back, ' . h($user['username']);
    if ((int)$user['is_admin'] === 1) echo ' (admin)';
    echo '. Your email: ' . h($user['email']) . '</div>';
}
if ($err) {
    echo '<div class="alert">' . h($err) . '</div>';
}

echo '<form method="post">';
echo '<label>Username</label>';
echo '<input type="text" name="username" value="' . h($_POST['username'] ?? '') . '">';
echo '<label>Password</label>';
echo '<input type="password" name="password">';
echo '<button type="submit">Sign in</button>';
echo '</form>';

layout_close();
