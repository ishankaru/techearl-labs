<?php
require __DIR__ . '/shared/layout.php';

/*
 * Vulnerable LFI sink #1: suffix-appended include.
 *
 * The intended design: callers pass a short page name like "about" or
 * "contact" and the handler renders pages/{name}.php. The fixed .php suffix
 * was added as a half-measure to "prevent" arbitrary file reads, on the
 * assumption that an attacker cannot persuade include() to open a file whose
 * real extension is not .php.
 *
 * That assumption is wrong twice:
 *
 *   1. php://filter ignores the trailing string after the resource name, so
 *      ?page=php://filter/convert.base64-encode/resource=index reads index.php
 *      as base64 and the appended ".php" simply becomes part of the wrapper
 *      argument string the engine discards.
 *
 *   2. php://input reads the POST body as if it were PHP source. With
 *      allow_url_include=On (set in the lab's php.ini) the engine runs that
 *      body, and again the trailing ".php" is irrelevant to the wrapper.
 *
 * No sanitisation. No allow-list. No realpath() check. No basename() strip.
 * Exactly the shape of every "rendered partial" anti-pattern that ships in
 * production PHP codebases.
 */
$page = $_GET['page'] ?? 'about';

layout_open('view.php');
echo '<h1>view.php</h1>';
echo '<p><small class="note">Including <code>pages/' . h($page) . '.php</code></small></p>';
echo '<div class="panel">';
include 'pages/' . $page . '.php';
echo '</div>';
layout_close();
