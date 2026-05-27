<?php
require __DIR__ . '/shared/layout.php';

/*
 * Vulnerable LFI sink #2: raw include, no suffix appended.
 *
 * The user-controlled value is passed straight to include(). This is the
 * textbook LFI shape every introductory write-up demonstrates against:
 *
 *   ?page=../../../../etc/passwd
 *
 * works directly because include() is happy to read any file the web user
 * can stat, regardless of extension. Files that contain no <?php tag are
 * dumped verbatim (everything outside PHP tags is echoed as output), which is
 * why /etc/passwd renders as plain text in the browser.
 *
 * The legitimate caller is expected to pass a relative path like
 * "pages/about.php"; the intended-use links on the home page do exactly that.
 * No sanitisation, no basename(), no allow-list, no realpath() containment.
 */
$page = $_GET['page'] ?? 'pages/about.php';

layout_open('view-raw.php');
echo '<h1>view-raw.php</h1>';
echo '<p><small class="note">Including <code>' . h($page) . '</code> verbatim</small></p>';
echo '<div class="panel">';
include $page;
echo '</div>';
layout_close();
