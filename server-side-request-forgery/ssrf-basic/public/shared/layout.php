<?php
/*
 * Shared layout helpers for the ssrf-basic lab. No database in this lab, so
 * this file is layout-only (the sqli-basic equivalent is shared/db.php).
 *
 * h() escapes for HTML context. Used to render the fetched response body
 * inside <pre> so that the response itself does not introduce XSS into the
 * lab page. The SSRF vulnerability is in the fetcher, not the renderer.
 */

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function layout_open(string $title): void {
    echo '<!doctype html><html lang="en"><head>';
    echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . ' | ssrf-basic lab</title>';
    echo '<style>
        body{font:14px/1.5 -apple-system,system-ui,sans-serif;max-width:820px;margin:2rem auto;padding:0 1rem;color:#0f172a}
        h1{font-size:1.4rem;margin-top:0}
        h2{font-size:1.1rem;margin-top:1.5rem}
        a{color:#0e7490}
        nav{margin-bottom:1.5rem;border-bottom:1px solid #e5e7eb;padding-bottom:.75rem}
        nav a{margin-right:1rem}
        form label{display:block;margin:.5rem 0 .25rem 0;font-size:12px;font-weight:600}
        form input[type=text],form input[type=url]{padding:.4rem .6rem;border:1px solid #cbd5e1;border-radius:.25rem;font-size:14px;width:100%;max-width:560px}
        form button{margin-top:.75rem;padding:.4rem 1rem;background:#0e7490;color:#fff;border:0;border-radius:.25rem;font-size:14px;cursor:pointer}
        pre{background:#0f172a;color:#e2e8f0;padding:.75rem 1rem;border-radius:.25rem;overflow-x:auto;font-size:12px;line-height:1.5}
        .alert{padding:.5rem .75rem;border-radius:.25rem;background:#fee2e2;color:#991b1b;font-size:13px}
        .ok{background:#dcfce7;color:#166534;padding:.5rem .75rem;border-radius:.25rem;font-size:13px}
        small.note{color:#64748b}
        code{background:#f1f5f9;padding:.1rem .3rem;border-radius:.2rem;font-size:13px}
    </style></head><body>';
    echo '<nav>';
    echo '<a href="/">Home</a>';
    echo '<a href="/fetch.php">URL Preview</a>';
    echo '<a href="/fetch-allowlist.php">Allowlist Demo</a>';
    echo '<a href="/fetch-blind.php">Blind Demo</a>';
    echo '</nav>';
}

function layout_close(): void {
    echo '<hr style="margin-top:2rem"><small class="note">techearl-labs / server-side-request-forgery / ssrf-basic</small>';
    echo '</body></html>';
}
