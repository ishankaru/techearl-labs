<?php
/*
 * Shared layout + escape helper for the rce-basic lab. No DB needed; every
 * vulnerability in this lab is a sink that hands user input straight to a
 * shell or a PHP interpreter, so there is nothing to connect to.
 *
 * h() is the only safe primitive in the lab: command/template output gets
 * rendered through it so the page itself does not double up as an XSS lab.
 */

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function layout_open(string $title): void {
    echo '<!doctype html><html lang="en"><head>';
    echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . ' | rce-basic lab</title>';
    echo '<style>
        body{font:14px/1.5 -apple-system,system-ui,sans-serif;max-width:760px;margin:2rem auto;padding:0 1rem;color:#0f172a}
        h1{font-size:1.4rem;margin-top:0}
        h2{font-size:1.1rem;margin-top:1.5rem}
        a{color:#0e7490}
        nav{margin-bottom:1.5rem;border-bottom:1px solid #e5e7eb;padding-bottom:.75rem}
        nav a{margin-right:1rem}
        .card{border:1px solid #e5e7eb;border-radius:.5rem;padding:1rem;margin:.75rem 0}
        form label{display:block;margin:.5rem 0 .25rem 0;font-size:12px;font-weight:600}
        form input[type=text],form textarea{padding:.4rem .6rem;border:1px solid #cbd5e1;border-radius:.25rem;font-size:14px;width:100%;max-width:480px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
        form textarea{min-height:80px}
        form button{margin-top:.75rem;padding:.4rem 1rem;background:#0e7490;color:#fff;border:0;border-radius:.25rem;font-size:14px;cursor:pointer}
        pre{background:#0f172a;color:#e2e8f0;padding:.75rem 1rem;border-radius:.25rem;overflow:auto;font-size:13px}
        .alert{padding:.5rem .75rem;border-radius:.25rem;background:#fee2e2;color:#991b1b;font-size:13px}
        small.note{color:#64748b}
    </style></head><body>';
    echo '<nav>'
        . '<a href="/">Home</a>'
        . '<a href="/ping.php">Ping</a>'
        . '<a href="/lookup.php">DNS lookup</a>'
        . '<a href="/template.php">Template</a>'
        . '<a href="/calc.php">Calculator</a>'
        . '</nav>';
}

function layout_close(): void {
    echo '<hr style="margin-top:2rem"><small class="note">techearl-labs / remote-code-execution / rce-basic</small>';
    echo '</body></html>';
}
