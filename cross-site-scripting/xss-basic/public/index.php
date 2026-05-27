<?php
/*
 * Landing page. Shows the latest comments from the guestbook, body rendered
 * raw, same vulnerability as guestbook.php. Any stored XSS payload submitted
 * via the guestbook also fires here on every visitor's first hit to /.
 *
 * The username column is escaped via h(). Only the comment body is the
 * unsafe sink, which matches what you usually see in the wild, attackers
 * land XSS in the long-text fields (comments, bios, descriptions) far more
 * often than in username slots that get tighter validation.
 */

require_once __DIR__ . '/shared/db.php';
$conn = db();

$rows = $conn->query(
    'SELECT c.id, c.body, c.created_at, u.username
     FROM comments c JOIN users u ON u.id = c.user_id
     ORDER BY c.id DESC
     LIMIT 5'
);

layout_open('Home');
echo '<h1>xss-basic</h1>';
echo '<p><small class="note">Deliberately vulnerable lab. See README.</small></p>';
echo '<h2>Recent comments</h2>';

while ($row = $rows->fetch_assoc()) {
    echo '<div class="comment">';
    echo '<div class="meta">' . h($row['username']) . ' &middot; ' . h($row['created_at']) . '</div>';
    // VULNERABLE: body is echoed raw. Stored XSS sink.
    echo '<div>' . $row['body'] . '</div>';
    echo '</div>';
}

echo '<p><a href="/guestbook.php">View all / post a comment &rarr;</a></p>';
layout_close();
