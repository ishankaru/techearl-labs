<?php
/*
 * VULNERABLE: stored XSS.
 *
 * Comment bodies are inserted via a parameterised statement (the data layer
 * is safe). They are then rendered back into the page raw, without
 * htmlspecialchars(). Any logged-in user can post `<script>alert(1)</script>`
 * as a comment body and it will fire for every subsequent visitor who loads
 * this page, plus the landing page (/), plus the admin page (/admin.php).
 *
 * The username is escaped via h(). Only the body is the unsafe sink, which
 * is the realistic shape, content fields are far more commonly missed than
 * identifier fields.
 *
 * Posting requires a login. That is what the cookie-theft chain exploits:
 * a stored payload submitted by alice fires when admin loads the page,
 * giving the attacker admin's session_id.
 */

require_once __DIR__ . '/shared/db.php';
$user = require_login();
$conn = db();

$err = null;
$ok = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = trim($_POST['body'] ?? '');
    if ($body === '') {
        $err = 'Comment cannot be empty.';
    } elseif (strlen($body) > 2000) {
        $err = 'Comment too long (2000 chars max).';
    } else {
        $stmt = $conn->prepare('INSERT INTO comments (user_id, body) VALUES (?, ?)');
        $stmt->bind_param('is', $user['id'], $body);
        if ($stmt->execute()) {
            $ok = 'Comment posted.';
        } else {
            $err = 'DB error: ' . $conn->error;
        }
    }
}

$rows = $conn->query(
    'SELECT c.id, c.body, c.created_at, u.username
     FROM comments c JOIN users u ON u.id = c.user_id
     ORDER BY c.id DESC'
);

layout_open('Guestbook');
echo '<h1>Guestbook</h1>';

if ($err) echo '<div class="alert">' . h($err) . '</div>';
if ($ok)  echo '<div class="alert ok">' . h($ok) . '</div>';

echo '<form method="post">';
echo '<label>Leave a comment as ' . h($user['username']) . '</label>';
echo '<textarea name="body" placeholder="Say something..."></textarea>';
echo '<button type="submit">Post</button>';
echo '</form>';

echo '<h2 style="margin-top:2rem">All comments</h2>';
while ($row = $rows->fetch_assoc()) {
    echo '<div class="comment">';
    echo '<div class="meta">' . h($row['username']) . ' &middot; ' . h($row['created_at']) . '</div>';
    // VULNERABLE: body echoed raw. Stored XSS sink.
    echo '<div>' . $row['body'] . '</div>';
    echo '</div>';
}

layout_close();
