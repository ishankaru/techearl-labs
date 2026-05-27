<?php
// Shared bootstrap for the CSRF lab. Sets a SameSite=Lax session cookie
// (the realistic 2026 default) and provides tiny helpers to load and persist
// the per-session bank account state. Storage is a flat JSON file under
// /tmp keyed by session id; entirely sufficient for a single-tenant lab.

declare(strict_types=1);

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'secure'   => false,         // lab runs over plain http on 127.0.0.1
    'samesite' => 'Lax',         // 2026 realistic default
]);
session_name('CSRFLABSESSID');
session_start();

function state_path(): string {
    return '/tmp/csrf-lab-' . session_id() . '.json';
}

function load_state(): array {
    $path = state_path();
    if (!is_file($path)) {
        $state = [
            'logged_in' => false,
            'user'      => null,
            'balance'   => 1000,
            'email'     => null,
            'log'       => [],
        ];
        file_put_contents($path, json_encode($state));
        return $state;
    }
    $raw = file_get_contents($path);
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function save_state(array $state): void {
    file_put_contents(state_path(), json_encode($state));
}

function append_log(array &$state, string $line): void {
    $state['log'][] = '[' . date('H:i:s') . '] ' . $line;
    if (count($state['log']) > 50) {
        $state['log'] = array_slice($state['log'], -50);
    }
}

function require_login(): array {
    $state = load_state();
    if (empty($state['logged_in'])) {
        http_response_code(401);
        header('Content-Type: text/plain');
        echo "Not logged in. POST username+password to /login.php first.\n";
        exit;
    }
    return $state;
}

function csrf_token(array &$state): string {
    if (empty($state['csrf'])) {
        $state['csrf'] = bin2hex(random_bytes(16));
        save_state($state);
    }
    return $state['csrf'];
}
