<?php
/*
 * Admin-only view of every comment ever submitted. Bodies rendered raw, same
 * unsafe sink as guestbook.php and index.php.
 *
 * This page is the concrete "stored XSS escalates to session hijack" target
 * the companion article walks through. The chain:
 *
 *   1. alice (regular user) posts a stored XSS payload as a comment body.
 *      Payload reads document.cookie and posts it to attacker.test.
 *   2. admin loads /admin.php to triage comments.
 *   3. The payload fires in admin's browser, exfiltrating admin's session_id
 *      cookie (which has no HttpOnly).
 *   4. The attacker replays the session_id cookie value against the lab and
 *      is now authenticated as admin.
 *
 * In real apps this is the "moderator account compromise via user content"
 * pattern, by far the most common way a stored XSS turns into a real
 * incident.
 */

require_once __DIR__ . '/shared/db.php';
$user = require_admin();
$conn = db();

$rows = $conn->query(
    'SELECT c.id, c.body, c.created_at, u.username
     FROM comments c JOIN users u ON u.id = c.user_id
     ORDER BY c.id DESC'
);

layout_open('Admin');
echo '<h1>Admin &middot; all comments</h1>';
echo '<p><small class="note">Signed in as ' . h($user['username']) . ' (admin).</small></p>';

while ($row = $rows->fetch_assoc()) {
    echo '<div class="comment">';
    echo '<div class="meta">#' . (int)$row['id'] . ' &middot; ' . h($row['username']) . ' &middot; ' . h($row['created_at']) . '</div>';
    // VULNERABLE: body rendered raw. Stored XSS fires in the admin's browser.
    echo '<div>' . $row['body'] . '</div>';
    echo '</div>';
}

layout_close();
