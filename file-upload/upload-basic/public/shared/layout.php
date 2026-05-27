<?php
/*
 * Shared layout helpers for the upload-basic lab. No database, no session,
 * no auth: this lab is purely about file-upload validation flaws, so the
 * shared layer is a thin chrome around four handler pages.
 */

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function layout_open(string $title): void {
    echo '<!doctype html><html lang="en"><head>';
    echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . ' | upload-basic lab</title>';
    echo '<style>
        body{font:14px/1.5 -apple-system,system-ui,sans-serif;max-width:760px;margin:2rem auto;padding:0 1rem;color:#0f172a}
        h1{font-size:1.4rem;margin-top:0}
        h2{font-size:1.05rem;margin-top:1.5rem}
        a{color:#0e7490}
        nav{margin-bottom:1.5rem;border-bottom:1px solid #e5e7eb;padding-bottom:.75rem}
        nav a{margin-right:1rem}
        .card{border:1px solid #e5e7eb;border-radius:.5rem;padding:1rem;margin:.75rem 0}
        form label{display:block;margin:.5rem 0 .25rem 0;font-size:12px;font-weight:600}
        form input[type=file]{padding:.4rem 0;font-size:14px}
        form button{margin-top:.75rem;padding:.4rem 1rem;background:#0e7490;color:#fff;border:0;border-radius:.25rem;font-size:14px;cursor:pointer}
        .alert{padding:.5rem .75rem;border-radius:.25rem;background:#fee2e2;color:#991b1b;font-size:13px;margin:.5rem 0}
        .ok{background:#dcfce7;color:#166534}
        code{background:#f1f5f9;padding:1px 4px;border-radius:3px;font-size:13px}
        small.note{color:#64748b}
    </style></head><body>';
    echo '<nav>';
    echo '<a href="/">Home</a>';
    echo '<a href="/upload-naive.php">Naive</a>';
    echo '<a href="/upload-blacklist.php">Blacklist</a>';
    echo '<a href="/upload-mime.php">MIME</a>';
    echo '<a href="/upload-double-ext.php">Double ext</a>';
    echo '</nav>';
}

function layout_close(): void {
    echo '<hr style="margin-top:2rem"><small class="note">techearl-labs / file-upload / upload-basic</small>';
    echo '</body></html>';
}
