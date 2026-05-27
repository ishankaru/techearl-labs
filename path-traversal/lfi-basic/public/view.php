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
 *   1. php://filter reads the include path as a wrapper invocation. With
 *      ?page=php://filter/convert.base64-encode/resource=pages/about the
 *      engine base64-encodes pages/about.php's source. The appended .php
 *      ends up as part of the resource= argument and is treated as a
 *      regular file path by the wrapper.
 *
 *   2. php://input technically does NOT match here. The PHP input wrapper
 *      compares the path string literally and "php://input.php" is not
 *      recognised, so the include falls through to "file not found". The
 *      RCE-via-POST-body chain therefore runs against view-raw.php instead.
 *      See README, scenario 3.
 *
 * No sanitisation. No allow-list. No realpath() check. No basename() strip.
 * Exactly the shape of every "rendered partial" anti-pattern that ships in
 * production PHP codebases.
 */
$page = $_GET['page'] ?? 'pages/about';

layout_open('view.php');
echo '<h1>view.php</h1>';
echo '<p><small class="note">Including <code>' . h($page) . '.php</code></small></p>';
echo '<div class="panel">';
include $page . '.php';
echo '</div>';
layout_close();
