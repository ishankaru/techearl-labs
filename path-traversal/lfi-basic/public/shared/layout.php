<?php
/*
 * Shared layout helpers for the lfi-basic lab. No DB; this lab is pure
 * filesystem and stream-wrapper abuse. Kept intentionally slim so the
 * vulnerable include() sinks in view.php / view-raw.php read cleanly in the
 * companion article's code excerpts.
 */

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function layout_open(string $title): void {
    echo '<!doctype html><html lang="en"><head>';
    echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . ' | lfi-basic lab</title>';
    echo '<style>
        body{font:14px/1.5 -apple-system,system-ui,sans-serif;max-width:760px;margin:2rem auto;padding:0 1rem;color:#0f172a}
        h1{font-size:1.4rem;margin-top:0}
        a{color:#0e7490}
        nav{margin-bottom:1.5rem;border-bottom:1px solid #e5e7eb;padding-bottom:.75rem}
        nav a{margin-right:1rem}
        .panel{border:1px solid #e5e7eb;border-radius:.5rem;padding:1rem;margin:.75rem 0}
        .panel h2{font-size:1rem;margin:0 0 .25rem 0}
        code,pre{font:13px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace;background:#f1f5f9;border-radius:.25rem}
        code{padding:.1rem .3rem}
        pre{padding:.6rem .8rem;overflow-x:auto;white-space:pre-wrap;word-break:break-all}
        small.note{color:#64748b}
    </style></head><body>';
    echo '<nav><a href="/">Home</a> <a href="/view.php?page=about">view.php?page=about</a> <a href="/view-raw.php?page=pages/about.php">view-raw.php?page=pages/about.php</a></nav>';
}

function layout_close(): void {
    echo '<hr style="margin-top:2rem"><small class="note">techearl-labs / path-traversal / lfi-basic</small>';
    echo '</body></html>';
}
