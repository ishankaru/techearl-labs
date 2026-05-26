<?php
/*
 * Shared DB connection helper for the sqli-basic lab. Returns a mysqli handle
 * configured to connect as the application user (`webapp`), NOT root.
 *
 * The connection itself is intentional: every other vulnerable file in this
 * lab uses string-concatenated queries against this handle to demonstrate
 * SQL injection.
 */

function db(): mysqli {
    static $conn = null;
    if ($conn !== null) {
        return $conn;
    }
    $host = getenv('DB_HOST') ?: 'sqli-basic-db';
    $user = getenv('DB_USER') ?: 'webapp';
    $pass = getenv('DB_PASS') ?: 'webapp123';
    $name = getenv('DB_NAME') ?: 'shop';

    // Retry briefly on container boot, MySQL can finish init seconds after
    // the healthcheck flips to healthy.
    $attempts = 0;
    while ($attempts < 12) {
        $conn = @new mysqli($host, $user, $pass, $name);
        if (!$conn->connect_error) {
            $conn->set_charset('utf8mb4');
            return $conn;
        }
        $attempts++;
        usleep(500_000);
    }
    http_response_code(503);
    die('DB connection failed: ' . htmlspecialchars($conn->connect_error ?? 'unknown'));
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function layout_open(string $title): void {
    echo '<!doctype html><html lang="en"><head>';
    echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . ' | sqli-basic lab</title>';
    echo '<style>
        body{font:14px/1.5 -apple-system,system-ui,sans-serif;max-width:760px;margin:2rem auto;padding:0 1rem;color:#0f172a}
        h1{font-size:1.4rem;margin-top:0}
        a{color:#0e7490}
        nav{margin-bottom:1.5rem;border-bottom:1px solid #e5e7eb;padding-bottom:.75rem}
        nav a{margin-right:1rem}
        .product{border:1px solid #e5e7eb;border-radius:.5rem;padding:1rem;margin:.75rem 0}
        .product h2{font-size:1rem;margin:0 0 .25rem 0}
        .price{font-weight:600;color:#0e7490}
        form label{display:block;margin:.5rem 0 .25rem 0;font-size:12px;font-weight:600}
        form input{padding:.4rem .6rem;border:1px solid #cbd5e1;border-radius:.25rem;font-size:14px;width:100%;max-width:300px}
        form button{margin-top:.75rem;padding:.4rem 1rem;background:#0e7490;color:#fff;border:0;border-radius:.25rem;font-size:14px;cursor:pointer}
        .alert{padding:.5rem .75rem;border-radius:.25rem;background:#fee2e2;color:#991b1b;font-size:13px}
        .ok{background:#dcfce7;color:#166534}
        small.note{color:#64748b}
    </style></head><body>';
    echo '<nav><a href="/">Products</a> <a href="/search.php">Search</a> <a href="/login.php">Login</a></nav>';
}

function layout_close(): void {
    echo '<hr style="margin-top:2rem"><small class="note">techearl-labs / sql-injection / sqli-basic</small>';
    echo '</body></html>';
}
