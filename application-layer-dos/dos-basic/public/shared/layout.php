<?php
/*
 * Shared layout + escape helper for the dos-basic lab. Mirrors the rce-basic
 * lab so the article output reads consistently across the security cluster.
 */

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function layout_open(string $title): void {
    echo '<!doctype html><html lang="en"><head>';
    echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . ' | dos-basic lab</title>';
    echo '<style>
        body{font:14px/1.5 -apple-system,system-ui,sans-serif;max-width:760px;margin:2rem auto;padding:0 1rem;color:#0f172a}
        h1{font-size:1.4rem;margin-top:0}
        h2{font-size:1.1rem;margin-top:1.5rem}
        a{color:#0e7490}
        nav{margin-bottom:1.5rem;border-bottom:1px solid #e5e7eb;padding-bottom:.75rem}
        nav a{margin-right:1rem}
        .card{border:1px solid #e5e7eb;border-radius:.5rem;padding:1rem;margin:.75rem 0}
        .card.defended{border-color:#10b981;background:#f0fdf4}
        form label{display:block;margin:.5rem 0 .25rem 0;font-size:12px;font-weight:600}
        form input[type=text],form textarea{padding:.4rem .6rem;border:1px solid #cbd5e1;border-radius:.25rem;font-size:14px;width:100%;max-width:480px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
        form textarea{min-height:80px}
        form button{margin-top:.75rem;padding:.4rem 1rem;background:#0e7490;color:#fff;border:0;border-radius:.25rem;font-size:14px;cursor:pointer}
        pre{background:#0f172a;color:#e2e8f0;padding:.75rem 1rem;border-radius:.25rem;overflow:auto;font-size:13px;white-space:pre-wrap;word-break:break-all}
        .alert{padding:.5rem .75rem;border-radius:.25rem;background:#fee2e2;color:#991b1b;font-size:13px}
        .ok{padding:.5rem .75rem;border-radius:.25rem;background:#dcfce7;color:#166534;font-size:13px}
        small.note{color:#64748b}
        table{border-collapse:collapse;width:100%;font-size:13px;margin:.5rem 0}
        th,td{border:1px solid #e5e7eb;padding:.4rem .6rem;text-align:left;vertical-align:top}
        th{background:#f8fafc}
    </style></head><body>';
    echo '<nav>'
        . '<a href="/">Home</a>'
        . '<a href="/search.php">ReDoS</a>'
        . '<a href="/upload.php">Decompression</a>'
        . '<a href="/slow.php">Slow body</a>'
        . '<a href="/billion-laughs.php">Billion laughs</a>'
        . '</nav>';
}

function layout_close(): void {
    echo '<hr style="margin-top:2rem"><small class="note">techearl-labs / application-layer-dos / dos-basic. Local container only.</small>';
    echo '</body></html>';
}
