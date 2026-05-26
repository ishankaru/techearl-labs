<?php
/*
 * VULNERABLE: string concat into a LIKE clause.
 *
 * q is single-quoted then concatenated, so the canonical payloads close the
 * quote and append. Demonstrates the same family of attacks but in a string
 * rather than numeric context.
 */

require_once __DIR__ . '/shared/db.php';
$conn = db();

$q = $_GET['q'] ?? '';

layout_open('Search');
echo '<h1>Search</h1>';

echo '<form method="get" action="/search.php">';
echo '<label>Search products</label>';
echo '<input type="text" name="q" value="' . h($q) . '" autofocus>';
echo '<button type="submit">Search</button>';
echo '</form>';

if ($q !== '') {
    $sql = "SELECT id, name, description FROM products WHERE name LIKE '%$q%' OR description LIKE '%$q%'";
    $result = $conn->query($sql);
    if ($result === false) {
        echo '<div class="alert">DB error: ' . h($conn->error) . '</div>';
    } else {
        $rows = 0;
        while ($row = $result->fetch_assoc()) {
            echo '<div class="product">';
            echo '<h2>' . h($row['name']) . '</h2>';
            echo '<div>' . h($row['description']) . '</div>';
            echo '</div>';
            $rows++;
        }
        if ($rows === 0) {
            echo '<div class="alert">No results for "' . h($q) . '"</div>';
        }
    }
}

layout_close();
