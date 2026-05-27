<?php
/*
 * Session helper for the xss-basic lab.
 *
 * The session cookie is called `session_id` (custom name, NOT PHP's PHPSESSID,
 * because we want full control of the cookie attributes and we are not using
 * PHP's session_start() machinery).
 *
 * INTENTIONAL MISCONFIGURATION: the cookie is set with `httponly => false`.
 * That means document.cookie can read it from JavaScript, which is exactly
 * how the companion XSS article chains any of the three XSS sinks into a
 * session-theft primitive. In real code this flag is always `true`. The
 * MDN docs for Set-Cookie spell out the rule: if a cookie does not need to
 * be read by JS, mark it HttpOnly. Session ids never need to be read by JS.
 *
 * SameSite=Lax is set so the cookie still rides along on top-level GET
 * navigations from the attacker's exfil page back to the lab origin, which
 * keeps the lab functional under the default Chrome cookie policy.
 */

require_once __DIR__ . '/db.php';

function session_cookie_set(string $id): void {
    setcookie('session_id', $id, [
        'expires'  => 0,
        'path'     => '/',
        'httponly' => false,   // intentional, see file header
        'samesite' => 'Lax',
        'secure'   => false,   // lab runs on http://localhost
    ]);
}

function session_cookie_clear(): void {
    setcookie('session_id', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => false,
        'samesite' => 'Lax',
        'secure'   => false,
    ]);
}

function current_user(): ?array {
    static $cached = false;
    static $user = null;
    if ($cached) return $user;
    $cached = true;

    $sid = $_COOKIE['session_id'] ?? '';
    if ($sid === '' || !preg_match('/^[a-f0-9]{64}$/', $sid)) {
        return $user = null;
    }

    $conn = db();
    $stmt = $conn->prepare(
        'SELECT u.id, u.username, u.email, u.is_admin
         FROM sessions s JOIN users u ON u.id = s.user_id
         WHERE s.id = ?'
    );
    $stmt->bind_param('s', $sid);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc() ?: null;
    return $user;
}

function require_login(): array {
    $u = current_user();
    if (!$u) {
        header('Location: /login.php');
        exit;
    }
    return $u;
}

function require_admin(): array {
    $u = require_login();
    if ((int)$u['is_admin'] !== 1) {
        http_response_code(403);
        die('Forbidden');
    }
    return $u;
}
