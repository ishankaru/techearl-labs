<?php
/*
 * /upload-blind.php, the blind XXE endpoint.
 *
 * Same unsafe DOMDocument configuration as /import.php, but the response
 * is hard-wired to "OK" or "Error". No parsed content is reflected back,
 * so in-band exfiltration ( &xxe; -> echoed file contents) does not work
 * here. This is the realistic shape: a backend XML processor that
 * accepts a document, does something internal with it, and signals only
 * success/failure.
 *
 * The attack path is parameter-entity-based, out-of-band:
 *
 *   1. Payload defines a parameter entity that loads an external DTD
 *      from http://xxe-basic-collab/evil.dtd (reachable on the Docker
 *      network as a sibling service).
 *   2. The DTD contains parameter entities that read a local file and
 *      embed its contents into the URL of a second parameter entity,
 *      then force that second entity's URL to be fetched.
 *   3. The lab container's libxml issues an HTTP request to the
 *      collaborator with the file contents in the URL path/query. The
 *      collaborator logs the request; the attacker reads the log.
 *
 * The response from THIS endpoint stays "OK" the whole time. The data
 * leaves via the side channel, not via the HTTP response.
 */

libxml_use_internal_errors(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain');
    echo "POST an XML body to this endpoint.\n";
    exit;
}

$body = file_get_contents('php://input');
if ($body === false || $body === '') {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo "Error\n";
    exit;
}

$dom = new DOMDocument();
// Same unsafe flags as /import.php. The vulnerability lives in the
// parser configuration, not in how the result is consumed.
$ok = @$dom->loadXML($body, LIBXML_NOENT | LIBXML_DTDLOAD | LIBXML_NOCDATA);

header('Content-Type: text/plain');
if ($ok) {
    echo "OK\n";
} else {
    http_response_code(400);
    echo "Error\n";
}
// Errors deliberately swallowed; libxml_clear_errors keeps the
// in-process error buffer from accumulating across requests.
libxml_clear_errors();
