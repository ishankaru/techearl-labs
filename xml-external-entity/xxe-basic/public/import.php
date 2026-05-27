<?php
require __DIR__ . '/shared/layout.php';

/*
 * /import.php, the in-band XXE endpoint.
 *
 * Accepts a raw XML body on POST, parses it with DOMDocument, and echoes
 * every <name> child of every <bookmark> back into the HTML response.
 *
 * The parser is configured with the unsafe libxml flag combination on
 * purpose:
 *
 *   LIBXML_NOENT     substitute entities (turning &xxe; into the resolved
 *                    content of file:///etc/passwd, for example). The flag
 *                    name is historically misleading: "NOENT" reads like
 *                    "no entities" but actually means "substitute entities
 *                    in the output tree".
 *   LIBXML_DTDLOAD   load external DTDs referenced by SYSTEM identifiers.
 *                    Without this, the SYSTEM "file:///..." in the inline
 *                    DOCTYPE would not be dereferenced.
 *   LIBXML_NOCDATA   merge CDATA into text nodes so the echo path is uniform.
 *
 * We additionally call $dom->xinclude() with LIBXML_NOENT to demonstrate
 * the XInclude attack flavour. XInclude is a separate libxml feature: it
 * is NOT triggered by LIBXML_NOENT alone, the application has to opt in
 * by calling xinclude() on the parsed document. Frameworks that "support
 * includes for templating" frequently do exactly this.
 *
 * libxml's built-in protection against billion-laughs / quadratic-blowup
 * entity expansion is still ON here. We do NOT pass LIBXML_PARSEHUGE.
 * That is the point of scenario 3 in the README: the parser refuses the
 * deeply-nested expansion and returns an Error. The lab demonstrates the
 * attack and the default mitigation together.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    layout_open('/import.php');
    echo '<h1>/import.php</h1>';
    echo '<p>POST an XML bookmark document to this endpoint. Example body:</p>';
    echo '<pre><code>' . h("<?xml version=\"1.0\"?>\n<bookmarks>\n  <bookmark><name>TechEarl</name><url>https://techearl.com</url></bookmark>\n</bookmarks>") . '</code></pre>';
    layout_close();
    exit;
}

$body = file_get_contents('php://input');
if ($body === false || $body === '') {
    http_response_code(400);
    layout_open('/import.php');
    echo '<h1>/import.php</h1><div class="alert">Empty request body.</div>';
    layout_close();
    exit;
}

libxml_use_internal_errors(true);

$dom = new DOMDocument();
// The unsafe combo. LIBXML_NOENT substitutes entities in the output tree;
// LIBXML_DTDLOAD allows the inline DOCTYPE to dereference SYSTEM URIs.
$ok = $dom->loadXML($body, LIBXML_NOENT | LIBXML_DTDLOAD | LIBXML_NOCDATA);

if (!$ok) {
    http_response_code(400);
    layout_open('/import.php');
    echo '<h1>/import.php</h1><div class="alert">XML parse error.</div>';
    echo '<pre>';
    foreach (libxml_get_errors() as $err) {
        echo h(trim($err->message)) . "\n";
    }
    echo '</pre>';
    libxml_clear_errors();
    layout_close();
    exit;
}

// XInclude processing, opt-in and separate from entity substitution. With
// this call, <xi:include href="file:///..." parse="text"/> nodes inside
// the document get replaced with the file contents inline.
$dom->xinclude(LIBXML_NOENT);

layout_open('/import.php');
echo '<h1>/import.php (parsed)</h1>';

$bookmarks = $dom->getElementsByTagName('bookmark');
if ($bookmarks->length === 0) {
    // Fall back to dumping the root element's text so XInclude payloads
    // that inject text directly under <bookmarks> still surface.
    echo '<div class="bookmark"><strong>(no &lt;bookmark&gt; nodes)</strong>';
    echo '<div>' . h(trim($dom->documentElement->textContent ?? '')) . '</div></div>';
} else {
    foreach ($bookmarks as $b) {
        $nameNode = $b->getElementsByTagName('name')->item(0);
        $urlNode  = $b->getElementsByTagName('url')->item(0);
        $name = $nameNode ? $nameNode->textContent : '(no name)';
        $url  = $urlNode  ? $urlNode->textContent  : '';
        echo '<div class="bookmark">';
        echo '<strong>' . h($name) . '</strong>';
        if ($url !== '') {
            echo '<div><small class="note">' . h($url) . '</small></div>';
        }
        echo '</div>';
    }
}
layout_close();
