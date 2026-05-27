<?php
/*
 * Tiny shared layout + session helper for the clickjacking-target lab.
 *
 * Session is PHP's default file-backed handler. The cookie defaults
 * (PHPSESSID, no SameSite override) are deliberately permissive for the
 * lab so the framed page receives the victim's session in the classical
 * iframe case. In a real app the cookie would be HttpOnly + Secure +
 * SameSite=Lax at minimum.
 *
 * The "deleted" flag is stored in the session itself so /dashboard can
 * show whether the prize action fired, without needing a database.
 */

function start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'samesite' => 'Lax',
            'httponly' => true,
            'secure'   => false,
        ]);
        session_start();
    }
}

function current_user(): ?string {
    start_session();
    return $_SESSION['user'] ?? null;
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function layout_open(string $title): void {
    echo "<!doctype html><html><head><meta charset=\"utf-8\">";
    echo "<title>" . h($title) . " &middot; clickjacking-target</title>";
    echo "<style>
        body{font-family:system-ui,sans-serif;max-width:640px;margin:2rem auto;padding:0 1rem;color:#222}
        h1{margin-bottom:.25rem} h2{margin-top:2rem}
        .nav a{margin-right:1rem}
        .note{color:#666;font-size:.9rem}
        button,.btn{background:#c0392b;color:#fff;border:0;padding:.6rem 1.2rem;border-radius:4px;font-size:1rem;cursor:pointer}
        .btn-ok{background:#2980b9}
        form{margin:1rem 0}
        input{padding:.4rem .6rem;font-size:1rem}
        .banner{background:#fffbe6;border:1px solid #f0c040;padding:.5rem .75rem;border-radius:4px}
    </style></head><body>";
    echo "<nav class=\"nav\"><a href=\"/\">home</a> <a href=\"/dashboard\">dashboard</a> <a href=\"/protected/dashboard\">protected/dashboard</a> <a href=\"/login\">login</a></nav>";
}

function layout_close(): void {
    echo "</body></html>";
}
