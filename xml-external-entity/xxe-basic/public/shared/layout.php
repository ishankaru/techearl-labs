<?php
/*
 * Shared layout helper for the xxe-basic lab. No DB; the lab parses XML
 * directly out of the request body, so there is nothing to connect to.
 *
 * h() is intentionally used only for rendering attacker-controlled content
 * back into HTML. It does NOT make the XML parser safe; the XXE happens
 * inside DOMDocument::loadXML() before any output escaping runs.
 */

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function layout_open(string $title): void {
    echo '<!doctype html><html lang="en"><head>';
    echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . ' | xxe-basic lab</title>';
    echo '<style>
        body{font:14px/1.5 -apple-system,system-ui,sans-serif;max-width:760px;margin:2rem auto;padding:0 1rem;color:#0f172a}
        h1{font-size:1.4rem;margin-top:0}
        h2{font-size:1.05rem;margin-top:1.5rem}
        a{color:#0e7490}
        nav{margin-bottom:1.5rem;border-bottom:1px solid #e5e7eb;padding-bottom:.75rem}
        nav a{margin-right:1rem}
        pre{background:#0f172a;color:#e2e8f0;padding:.75rem 1rem;border-radius:.5rem;overflow-x:auto;font-size:12px;line-height:1.5}
        code{background:#f1f5f9;padding:.1rem .35rem;border-radius:.25rem;font-size:12px}
        pre code{background:transparent;padding:0}
        .bookmark{border:1px solid #e5e7eb;border-radius:.5rem;padding:.75rem 1rem;margin:.5rem 0}
        .bookmark strong{display:block;margin-bottom:.25rem}
        .alert{padding:.5rem .75rem;border-radius:.25rem;background:#fee2e2;color:#991b1b;font-size:13px}
        .ok{background:#dcfce7;color:#166534;padding:.5rem .75rem;border-radius:.25rem;font-size:13px}
        small.note{color:#64748b}
    </style></head><body>';
    echo '<nav><a href="/">Home</a> <a href="/import.php">/import.php</a> <a href="/upload-blind.php">/upload-blind.php</a></nav>';
}

function layout_close(): void {
    echo '<hr style="margin-top:2rem"><small class="note">techearl-labs / xml-external-entity / xxe-basic</small>';
    echo '</body></html>';
}
